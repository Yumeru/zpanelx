<?php

/**
 *
 * ZPanel - A Cross-Platform Open-Source Web Hosting Control panel.
 * 
 * @package ZPanel
 * @version $Id$
 * @author Bobby Allen - ballen@zpanelcp.com
 * @copyright (c) 2008-2011 ZPanel Group - http://www.zpanelcp.com/
 * @license http://opensource.org/licenses/gpl-3.0.html GNU Public License v3
 *
 * This program (ZPanel) is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
class module_controller {

    static $complete;
    static $error;
    static $writeerror;
    static $nosub;
    static $alreadyexists;
    static $badname;
    static $blank;
    static $ok;

    /**
     * The 'worker' methods.
     */
    static function ListSubDomains($uid) {
        global $zdbh;
        $sql = "SELECT * FROM x_vhosts WHERE vh_acc_fk=" . $uid . " AND vh_deleted_ts IS NULL AND vh_type_in=2 ORDER BY vh_name_vc ASC";
        $numrows = $zdbh->query($sql);
        if ($numrows->fetchColumn() <> 0) {
            $sql = $zdbh->prepare($sql);
            $res = array();
            $sql->execute();
            while ($rowdomains = $sql->fetch()) {
                array_push($res, array('subname' => $rowdomains['vh_name_vc'],
                    'subdirectory' => $rowdomains['vh_directory_vc'],
                    'subactive' => $rowdomains['vh_active_in'],
                    'subid' => $rowdomains['vh_id_pk']));
            }
            return $res;
        } else {
            return false;
        }
    }

    static function ListDomains($uid) {
        global $zdbh;
        $sql = "SELECT * FROM x_vhosts WHERE vh_acc_fk=" . $uid . " AND vh_deleted_ts IS NULL AND vh_type_in=1 ORDER BY vh_name_vc ASC";
        $numrows = $zdbh->query($sql);
        if ($numrows->fetchColumn() <> 0) {
            $sql = $zdbh->prepare($sql);
            $res = array();
            $sql->execute();
            while ($rowdomains = $sql->fetch()) {
                array_push($res, array('name' => $rowdomains['vh_name_vc'],
                    'directory' => $rowdomains['vh_directory_vc'],
                    'active' => $rowdomains['vh_active_in'],
                    'id' => $rowdomains['vh_id_pk']));
            }
            return $res;
        } else {
            return false;
        }
    }

    static function ListDomainDirs($uid) {
        global $controller;
        $currentuser = ctrl_users::GetUserDetail($uid);
        $res = array();
        $handle = @opendir(ctrl_options::GetSystemOption('hosted_dir') . $currentuser['username'] . "/public_html");
        $chkdir = ctrl_options::GetSystemOption('hosted_dir') . $currentuser['username'] . "/public_html/";
        if (!$handle) {
            # Log an error as the folder cannot be opened...
        } else {
            while ($file = @readdir($handle)) {
                if ($file != "." && $file != ".." && $file != "_errorpages") {
                    if (is_dir($chkdir . $file)) {
                        array_push($res, array('domains' => $file));
                    }
                }
            }
            closedir($handle);
        }
        return $res;
    }

    static function ExecuteDeleteSubDomain($id) {
        global $zdbh;
        $retval = FALSE;
        runtime_hook::Execute('OnBeforeDeleteSubDomain');
        $sql = $zdbh->prepare("UPDATE x_vhosts 
							   SET vh_deleted_ts=" . time() . " 
							   WHERE vh_id_pk=" . $id . "");
        $sql->execute();
        self::SetWriteApacheConfigTrue();
        $retval = TRUE;
        runtime_hook::Execute('OnAfterDeleteSubDomain');
        return $retval;
    }

    public function ExecuteAddSubDomain($uid, $domain, $destination, $autohome) {
        global $zdbh;
        global $controller;
        $retval = FALSE;
        runtime_hook::Execute('OnBeforeAddSubDomain');
        $currentuser = ctrl_users::GetUserDetail($uid);
        $domain = strtolower(str_replace(' ', '', $domain));
        if (!fs_director::CheckForEmptyValue(self::CheckCreateForErrors($domain))) {
            //** New Home Directory **//
            if ($autohome == 1) {
                $destination = "/" . str_replace(".", "_", $domain);
                $vhost_path = ctrl_options::GetSystemOption('hosted_dir') . $currentuser['username'] . "/public_html/" . $destination . "/";
                fs_director::CreateDirectory($vhost_path);
                //** Existing Home Directory **//
            } else {
                $destination = "/" . $destination;
                $vhost_path = ctrl_options::GetSystemOption('hosted_dir') . $currentuser['username'] . "/public_html/" . $destination . "/";
            }
            // Error documents:- Error pages are added automatically if they are found in the _errorpages directory
            // and if they are a valid error code, and saved in the proper format, i.e. <error_number>.html
            fs_director::CreateDirectory($vhost_path . "/_errorpages/");
            $errorpages = ctrl_options::GetSystemOption('static_dir') . "/errorpages/";
            if (is_dir($errorpages)) {
                if ($handle = @opendir($errorpages)) {
                    while (($file = @readdir($handle)) !== false) {
                        if ($file != "." && $file != "..") {
                            $page = explode(".", $file);
                            if (!fs_director::CheckForEmptyValue(self::CheckErrorDocument($page[0]))) {
                                fs_filehandler::CopyFile($errorpages . $file, $vhost_path . '/_errorpages/' . $file);
                            }
                        }
                    }
                    closedir($handle);
                }
            }
            // Lets copy the default welcome page across...
            if ((!file_exists($vhost_path . "/index.html")) && (!file_exists($vhost_path . "/index.php")) && (!file_exists($vhost_path . "/index.htm"))) {
                fs_filehandler::CopyFileSafe(ctrl_options::GetSystemOption('static_dir') . "pages/welcome.html", $vhost_path . "/index.html");
            }
            // If all has gone well we need to now create the domain in the database...
            $sql = $zdbh->prepare("INSERT INTO x_vhosts (vh_acc_fk,
														 vh_name_vc,
														 vh_directory_vc,
														 vh_type_in,
														 vh_created_ts) VALUES (
														 " . $currentuser['userid'] . ",
														 '" . $domain . "',
														 '" . $destination . "',
														 2,
														 " . time() . ")"); //CLEANER FUNCTION ON $domain and $homedirectory_to_use (Think I got it?)
            $sql->execute();
            # Only run if the Server platform is Windows.
            if (sys_versions::ShowOSPlatformVersion() == 'Windows') {
                if (ctrl_options::GetSystemOption('disable_hostsen') == 'false') {
                    # Lets add the hostname to the HOSTS file so that the server can view the domain immediately...
                    @exec("C:/zpanel/bin/zpss/setroute.exe " . $domain . "");
                }
            }
            self::SetWriteApacheConfigTrue();
            $retval = TRUE;
            runtime_hook::Execute('OnAfterAddSubDomain');
            return $retval;
        }
    }

    static function CheckCreateForErrors($domain) {
        global $zdbh;
        // Check for spaces and remove if found...
        $domain = strtolower(str_replace(' ', '', $domain));
        // Check to make sure the domain is not blank before we go any further...
        if ($domain == '') {
            self::$blank = TRUE;
            return FALSE;
        }
        // Check for invalid characters in the domain...
        if (!self::IsValidDomainName($domain)) {
            self::$badname = TRUE;
            return FALSE;
        }
        // Check to make sure the domain is in the correct format before we go any further...
        $wwwclean = stristr($domain, 'www.');
        if ($wwwclean == true) {
            self::$error = TRUE;
            return FALSE;
        }
        // Check to see if the domain already exists in ZPanel somewhere and redirect if it does....
        $sql = "SELECT COUNT(*) FROM x_vhosts WHERE vh_name_vc='" . $domain . "' AND vh_deleted_ts IS NULL";
        if ($numrows = $zdbh->query($sql)) {
            if ($numrows->fetchColumn() > 0) {
                self::$alreadyexists = TRUE;
                return FALSE;
            }
        }
        return TRUE;
    }

    static function CheckErrorDocument($error) {
        $errordocs = array(100, 101, 102, 200, 201, 202, 203, 204, 205, 206, 207,
            300, 301, 302, 303, 304, 305, 306, 307, 400, 401, 402,
            403, 404, 405, 406, 407, 408, 409, 410, 411, 412, 413,
            414, 415, 416, 417, 418, 419, 420, 421, 422, 423, 424,
            425, 426, 500, 501, 502, 503, 504, 505, 506, 507, 508,
            509, 510);
        if (in_array($error, $errordocs)) {
            return true;
        } else {
            return false;
        }
    }

    static function IsValidDomainName($a) {
        if (stristr($a, '.')) {
            $part = explode(".", $a);
            foreach ($part as $check) {
                if (!preg_match('/^[a-z\d][a-z\d-]{0,62}$/i', $check) || preg_match('/-$/', $check)) {
                    return false;
                }
            }
        } else {
            return false;
        }
        return true;
    }

    static function IsValidEmail($email) {
        if (!preg_match('/^[a-z0-9]+([_\\.-][a-z0-9]+)*@([a-z0-9]+([\.-][a-z0-9]+)*)+\\.[a-z]{2,}$/i', $email)) {
            return false;
        }
        return true;
    }

    static function SetWriteApacheConfigTrue() {
        global $zdbh;
        $sql = $zdbh->prepare("UPDATE x_settings
								SET so_value_tx='true'
								WHERE so_name_vc='apache_changed'");
        $sql->execute();
    }

    /**
     * End 'worker' methods.
     */

    /**
     * Webinterface sudo methods.
     */
    static function getSubDomainList() {
        global $controller;
        $currentuser = ctrl_users::GetUserDetail();
        $res = array();
        $subdomains = self::ListSubDomains($currentuser['userid']);
        if (!fs_director::CheckForEmptyValue($subdomains)) {
            foreach ($subdomains as $row) {
                $status = self::getSubDomainStatusHTML($row['subactive'], $row['subid']);
                array_push($res, array('subname' => $row['subname'],
                    'subdirectory' => $row['subdirectory'],
                    'subactive' => $row['subactive'],
                    'substatus' => $status,
                    'subid' => $row['subid']));
            }
            return $res;
        } else {
            return false;
        }
    }

    static function getDomainList() {
        global $controller;
        $currentuser = ctrl_users::GetUserDetail();
        $domains = self::ListDomains($currentuser['userid']);
        if (!fs_director::CheckForEmptyValue($domains)) {
            return $domains;
        } else {
            return false;
        }
    }

    static function getCreateSubDomain() {
        global $zdbh;
        global $controller;
        $currentuser = ctrl_users::GetUserDetail();
        if ($currentuser['subdomainquota'] > ctrl_users::GetQuotaUsages('subdomains', $currentuser['userid'])) {
            return true;
        } else {
            return false;
        }
    }

    static function getSubDomainDirsList() {
        global $zdbh;
        global $controller;
        $currentuser = ctrl_users::GetUserDetail();
        $domaindirectories = self::ListDomainDirs($currentuser['userid']);
        if (!fs_director::CheckForEmptyValue($domaindirectories)) {
            return $domaindirectories;
        } else {
            return false;
        }
    }

    static function doCreateSubDomain() {
        global $controller;
        $currentuser = ctrl_users::GetUserDetail();
        $formvars = $controller->GetAllControllerRequests('FORM');
        if (self::ExecuteAddSubDomain($currentuser['userid'], $formvars['inSub'] . "." . $formvars['inDomain'], $formvars['inDestination'], $formvars['inAutoHome'])) {
            self::$ok = TRUE;
            return true;
        } else {
            return false;
        }
        return;
    }

    static function doDeleteSubDomain() {
        global $controller;
        $currentuser = ctrl_users::GetUserDetail();
        $formvars = $controller->GetAllControllerRequests('FORM');
        if (isset($formvars['inDelete'])) {
            if (self::ExecuteDeleteSubDomain($formvars['inDelete'])) {
                self::$ok = TRUE;
                return true;
            }
        }
        return false;
    }

    static function doConfirmDeleteSubDomain() {
        global $controller;
        $currentuser = ctrl_users::GetUserDetail();
        $formvars = $controller->GetAllControllerRequests('FORM');
        foreach (self::ListSubDomains($currentuser['userid']) as $row) {
            if (isset($formvars['inDelete_' . $row['subid'] . ''])) {
                header("location: ./?module=" . $controller->GetCurrentModule() . "&show=Delete&id=" . $row['subid'] . "&domain=" . $row['subname'] . "");
                exit;
            }
        }
        return false;
    }

    static function getisDeleteDomain() {
        global $controller;
        $urlvars = $controller->GetAllControllerRequests('URL');
        if ((isset($urlvars['show'])) && ($urlvars['show'] == "Delete"))
            return true;
        return false;
    }

    static function getCurrentID() {
        global $controller;
        if ($controller->GetControllerRequest('URL', 'id')) {
            return $controller->GetControllerRequest('URL', 'id');
        } else {
            return "";
        }
    }

    static function getCurrentDomain() {
        global $controller;
        if ($controller->GetControllerRequest('URL', 'domain')) {
            return $controller->GetControllerRequest('URL', 'domain');
        } else {
            return "";
        }
    }

    static function getModuleName() {
        $module_name = ui_module::GetModuleName();
        return $module_name;
    }

    static function getModuleIcon() {
        global $controller;
        $module_icon = "/modules/" . $controller->GetControllerRequest('URL', 'module') . "/assets/icon.png";
        return $module_icon;
    }

    static function getModuleDesc() {
        $message = ui_language::translate(ui_module::GetModuleDescription());
        return $message;
    }

    static function getSubDomainUsagepChart() {
        global $controller;
        $currentuser = ctrl_users::GetUserDetail();
        $line = "";
        $total = $currentuser['subdomainquota'];
        $used = ctrl_users::GetQuotaUsages('subdomains', $currentuser['userid']);
        $free = $total - $used;
        $line .= "<img src=\"etc/lib/pChart2/zpanel/z3DPie.php?score=" . $free . "::" . $used . "&labels=Free: " . $free . "::Used: " . $used . "&legendfont=verdana&legendfontsize=8&imagesize=240::190&chartsize=120::90&radius=100&legendsize=150::160\"/>";
        return $line;
    }

    static function getSubDomainStatusHTML($int, $id) {
        global $controller;
        if ($int == 1) {
            return "<td><font color=\"green\">" . ui_language::translate("Live") . "</font></td><td></td>";
        } else {
            return "<td><font color=\"orange\">" . ui_language::translate("Pending") . "</font></td><td><a href=\"#\" class=\"help_small\" id=\"help_small_" . $id . "_a\" title=\"" . ui_language::translate("Your domain will become active at the next scheduled update.  This can take up to one hour.") . "\"><img src=\"/modules/" . $controller->GetControllerRequest('URL', 'module') . "/assets/help_small.png\" border=\"0\" /></a>";
        }
    }

    static function getResult() {
        if (!fs_director::CheckForEmptyValue(self::$blank)) {
            return ui_sysmessage::shout(ui_language::translate("Your Domain can not be empty. Please enter a valid Domain Name and try again."), "zannounceerror");
        }
        if (!fs_director::CheckForEmptyValue(self::$badname)) {
            return ui_sysmessage::shout(ui_language::translate("Your Domain name is not valid. Please enter a valid Domain Name: i.e. 'domain.com'"), "zannounceerror");
        }
        if (!fs_director::CheckForEmptyValue(self::$alreadyexists)) {
            return ui_sysmessage::shout(ui_language::translate("The domain already appears to exsist on this server."), "zannounceerror");
        }
        if (!fs_director::CheckForEmptyValue(self::$error)) {
            return ui_sysmessage::shout(ui_language::translate("Please remove 'www'. The 'www' will automatically work with all Domains / Subdomains."), "zannounceerror");
        }
        if (!fs_director::CheckForEmptyValue(self::$writeerror)) {
            return ui_sysmessage::shout(ui_language::translate("There was a problem writting to the virtual host container file. Please contact your administrator and report this error. Your domain will not function until this error is corrected."), "zannounceerror");
        }
        if (!fs_director::CheckForEmptyValue(self::$ok)) {
            return ui_sysmessage::shout(ui_language::translate("Changes to your domain web hosting has been saved successfully."), "zannounceok");
        }
        return;
    }

    /**
     * Webinterface sudo methods.
     */
}

?>