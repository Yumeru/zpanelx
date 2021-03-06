<?php

/*
  +-----------------------------------------------------------------------+
  | program/steps/settings/identities.inc                                 |
  |                                                                       |
  | This file is part of the Roundcube Webmail client                     |
  | Copyright (C) 2005-2007, The Roundcube Dev Team                       |
  | Licensed under the GNU GPL                                            |
  |                                                                       |
  | PURPOSE:                                                              |
  |   Manage identities of a user account                                 |
  |                                                                       |
  +-----------------------------------------------------------------------+
  | Author: Thomas Bruederli <roundcube@gmail.com>                        |
  +-----------------------------------------------------------------------+

  $Id: identities.inc 4410 2011-01-12 18:25:02Z thomasb $

 */

define('IDENTITIES_LEVEL', intval($RCMAIL->config->get('identities_level', 0)));

$OUTPUT->set_pagetitle(rcube_label('identities'));

$OUTPUT->include_script('list.js');

// similar function as /steps/addressbook/func.inc::rcmail_contact_frame()
function rcmail_identity_frame($attrib) {
    global $OUTPUT;

    if (!$attrib['id'])
        $attrib['id'] = 'rcmIdentityFrame';

    $attrib['name'] = $attrib['id'];

    $OUTPUT->set_env('contentframe', $attrib['name']);

    return html::iframe($attrib);
}

$OUTPUT->add_handler('identityframe', 'rcmail_identity_frame');
$OUTPUT->set_env('identities_level', IDENTITIES_LEVEL);

$OUTPUT->send('identities');
