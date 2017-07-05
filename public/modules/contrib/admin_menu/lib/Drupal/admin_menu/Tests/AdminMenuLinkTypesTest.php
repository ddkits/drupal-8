<?php

/**
 * @file
 * Contains Drupal\admin_menu\Tests\AdminMenuLinkTypesTest.
 */

namespace Drupal\admin_menu\Tests;

/**
 * Tests appearance of different types of links.
 */
class AdminMenuLinkTypesTest extends AdminMenuTestBase {

  public static $modules = array('help');

  public static function getInfo() {
    return array(
      'name' => 'Link types',
      'description' => 'Tests appearance of different types of links.',
      'group' => 'Administration menu',
    );
  }

  function setUp() {
    parent::setUp();

    $this->drupalLogin($this->root_user);
  }

  /**
   * Tests appearance of different router item link types.
   */
  function testLinkTypes() {
    // Verify that MENU_NORMAL_ITEMs appear.
    $this->assertLinkTrailByTitle(array(
      t('Configuration'),
      t('System'),
      t('Site information'),
    ));

    // Verify that MENU_LOCAL_TASKs appear.
    $this->assertLinkTrailByTitle(array(t('People'), t('Permissions')));
    $this->assertLinkTrailByTitle(array(t('Appearance'), t('Settings')));
    $this->assertLinkTrailByTitle(array(t('Extend'), t('Uninstall')));

    // Verify that MENU_LOCAL_ACTIONs appear.
    $this->assertLinkTrailByTitle(array(
      t('People'),
      t('Add user'),
    ));

    // Verify that MENU_DEFAULT_LOCAL_TASKs do NOT appear.
    $this->assertNoLinkTrailByTitle(array(t('Extend'), t('List')));
    $this->assertNoLinkTrailByTitle(array(t('People'), t('List')));
    $this->assertNoLinkTrailByTitle(array(t('People'), t('Permissions'), t('Permissions')));
    $this->assertNoLinkTrailByTitle(array(t('Appearance'), t('List')));

    // Verify that MENU_VISIBLE_IN_BREADCRUMB items (exact type) do NOT appear.
    $this->assertNoLinkTrailByTitle(array(t('Extend'), t('Uninstall'), t('Uninstall')));
    $this->assertNoLinkTrailByTitle(array(t('Help'), 'admin_menu'));

    // Verify that special "Index" link appears below icon.
    $this->assertElementByXPath('//div[@id="admin-menu"]//a[contains(@href, :path) and text()=:title]', array(
      ':path' => 'admin/index',
      ':title' => t('Index'),
    ), "Icon » Index link found.");
  }
}

