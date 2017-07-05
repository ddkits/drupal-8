<?php

/**
 * @file
 * Views integration for the Login Tracker module.
 */

/**
 * Implements hook_views_data().
 */
function login_tracker_views_data() {
  $data = array();
  $data['login_tracker']['table']['group'] = t('User logins');
  $data['login_tracker']['table']['base'] = array(
    'field' => 'record_id',
    'title' => t('User logins'),
    'help'  => t('Contains data about user logins.'),
  );
  $data['login_tracker']['table']['join'] = array(
    'users_field_data' => array(
      'left_field' => 'uid',
      'field'      => 'uid',
    ),
  );
  // The UID field.
  $data['login_tracker']['record_id'] = array(
    'title' => t('Login record ID'),
    'help'  => t("The unique ID referring to this login record."),
    'field' => array(
      'id' => 'numeric',
    ),
    'sort' => array(
      'id' => 'standard',
    ),
    'filter' => array(
      'title' => t('Record ID'),
      'id' => 'numeric',
    ),
  );
  $data['login_tracker']['uid'] = array(
    'title' => t('UID'),
    'help'  => t("The user's UID."),
    'field' => array(
      'id' => 'user',
    ),
    'sort' => array(
      'id' => 'standard',
    ),
    'filter' => array(
      'title' => t('UID'),
      'id' => 'numeric',
    ),
    'relationship' => array(
      'title'              => t('User who logged in'),
      'help'               => t('The user associated with the login record.'),
      'id'                 => 'standard',
      'base'               => 'users_field_data',
      'base field'         => 'uid',
      'field'              => 'uid',
      'label'              => t('User who logged in'),
    ),
  );
  // The login timestamp field.
  $data['login_tracker']['login_timestamp'] = array(
    'title'  => t('Login timestamp'),
    'help'   => t('The timestamp that the user logged in at.'),
    'field'  => array(
      'id' => 'date',
    ),
    'sort'   => array(
      'id' => 'date',
    ),
    'filter' => array(
      'handler' => 'date',
    ),
  );
  $data['login_tracker']['data'] = array(
    'title' => t('Login data'),
    'help'  => t('Login record information.'),
    'field' => array(
      'id'             => 'serialized',
      'click sortable' => FALSE,
    ),
    'sort' => array(
      'id' => 'standard',
    ),
    'filter' => array(
      'handler' => 'serialized',
    ),
  );
  return $data;
}
