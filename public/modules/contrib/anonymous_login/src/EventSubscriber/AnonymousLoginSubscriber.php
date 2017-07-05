<?php

namespace Drupal\anonymous_login\EventSubscriber;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcher;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\State\State;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * Class AnonymousLoginSubscriber.
 *
 * @package Drupal\anonymous_login
 */
class AnonymousLoginSubscriber implements EventSubscriberInterface {

  /**
   * The current path for the current request.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $pathCurrent;

  /**
   * The configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The state system.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * The instantiated account.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * The path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcher
   */
  protected $pathMatcher;

  /**
   * Paths textarea line break.
   *
   * @var string
   */
  protected $pathsLineBreak = "\n";

  /**
   * Constructor of a new AnonymousLoginSubscriber.
   *
   * @param \Drupal\Core\Path\CurrentPathStack $path_current
   *   The current path for the current request.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The configuration object factory.
   * @param \Drupal\Core\State\State $state
   *   The state system.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The instantiated account.
   * @param \Drupal\Core\Path\PathMatcher $path_matcher
   *   The path matcher.
   */
  public function __construct(CurrentPathStack $path_current, ConfigFactory $config_factory, State $state, AccountProxy $current_user, PathMatcher $path_matcher) {
    $this->pathCurrent = $path_current;
    $this->configFactory = $config_factory;
    $this->state = $state;
    $this->currentUser = $current_user;
    $this->pathMatcher = $path_matcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['redirect', 100];
    return $events;
  }

  /**
   * Perform the anonymous user redirection, if needed.
   *
   * This method is called whenever the KernelEvents::REQUEST event is
   * dispatched.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The Event to process.
   */
  public function redirect(GetResponseEvent $event) {
    // Skip if maintenance mode is enabled.
    if ($this->state->get('system.maintenance_mode')) {
      return;
    }

    // Skip if running from the command-line.
    if (PHP_SAPI === 'cli') {
      return;
    }

    // Skip if no paths are configured for redirecting.
    if (!($paths = $this->paths()) || empty($paths['include'])) {
      return;
    }

    // Skip if the user is not anonymous.
    if (!$this->currentUser->isAnonymous()) {
      return;
    }

    // Determine the current path and alias.
    $current = [
      'path' => $this->pathCurrent->getPath(),
      'alias' => \Drupal::request()->getRequestUri(),
    ];

    // Ignore PHP file requests.
    if (substr($current['path'], -4) == '.php') {
      return;
    }

    // Ignore the user login page.
    $login_page = ($this->configFactory->get('anonymous_login.settings')->get('login_path')) ? $this->configFactory->get('anonymous_login.settings')->get('login_path') : '/user/login';
    if ($current['path'] == $login_page) {
      return;
    }

    // Convert the path to the front page token, if needed.
    $front_page = $this->configFactory->get('system.site')->get('page.front');
    $current['path'] = ($current['path'] != $front_page) ? $current['path'] : '<front>';

    // Track if we should redirect.
    $redirect = FALSE;

    // Iterate the current path and alias.
    foreach ($current as &$check) {
      // Remove the leading or trailer slash.
      $check = $this->pathSlashCut($check);

      // Redirect if the path is a match for included paths.
      if ($this->pathMatcher->matchPath($check, implode($this->pathsLineBreak, $paths['include']))) {
        $redirect = TRUE;
      }
      // Do not redirect if the path is a match for excluded paths.
      if (!empty($paths['exclude']) && $this->pathMatcher->matchPath($check, implode($this->pathsLineBreak, $paths['exclude']))) {
        $redirect = FALSE;
        // Matching an excluded path is a hard-stop.
        break;
      }
    }

    // See if we're going to redirect.
    if ($redirect) {
      // See if we have a message to display.
      if ($message = $this->configFactory->get('anonymous_login.settings')->get('message')) {
        // @todo: translation?
        drupal_set_message($message);
      }

      // Redirect to the login, keeping the requested alias as the destination.
      $event->setResponse(new RedirectResponse($login_page . '?destination=' . $current['alias']));
    }
  }

  /**
   * Fetch the paths.
   *
   * That should be used when determining when to force
   * anonymous users to login.
   *
   * @return array
   *   An array of paths, keyed by "include", paths that should force a
   *   login, and "exclude", paths that should be ignored.
   */
  public function paths() {
    // Initialize the paths array.
    $paths = [
      'include' => [],
      'exclude' => [],
    ];

    // Fetch the stored paths set in the admin settings.
    if ($setting = $this->configFactory->get('anonymous_login.settings')->get('paths')) {
      // Set paths line break.
      if (strpos($setting, "\r\n")) {
        $this->pathsLineBreak = "\r\n";
      }

      // Split by each newline.
      $setting = explode($this->pathsLineBreak, $setting);

      // Iterate each path and determine if the path should be included
      // or excluded.
      foreach ($setting as $path) {
        if (substr($path, 0, 1) == '~') {
          $path = substr($path, 1);
          $path = $this->pathSlashCut($path);
          $paths['exclude'][] = $path;
        }
        else {
          $path = $this->pathSlashCut($path);
          $paths['include'][] = $path;
        }
      }
    }

    // Always exclude certain paths.
    $paths['exclude'][] = 'user/reset/*';
    $paths['exclude'][] = 'cron/*';

    // Allow other modules to alter the paths.
    \Drupal::moduleHandler()->alter('anonymous_login_paths', $paths);

    return $paths;
  }

  /**
   * Cut leading and trailer slashes, if needed.
   *
   * @param string $path_string
   *   String which contains page path.
   *
   * @return string
   *   String which contains clean page path.
   */
  public function pathSlashCut($path_string) {
    // Remove the leading slash.
    if (substr($path_string, 0, 1) == '/') {
      $path_string = substr($path_string, 1);
    }

    // Remove the trailer slash.
    if (substr($path_string, -1) == '/') {
      $path_string = substr($path_string, 0, strlen($path_string) - 1);
    }

    return $path_string;
  }

}
