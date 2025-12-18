<?php
/**
 * ZeroTier
 * @package project
 * @author Wizard <sergejey@gmail.com>
 * @copyright http://majordomo.smartliving.ru/ (c)
 * @version 0.1 (wizard, 12:08:35 [Aug 01, 2020])
 */
//
//
class zerotier extends module
{
    /**
     * zerotier
     *
     * Module class constructor
     *
     * @access private
     */
    function __construct()
    {
        $this->name = "zerotier";
        $this->title = "ZeroTier";
        $this->module_category = "<#LANG_SECTION_SYSTEM#>";
        $this->checkInstalled();
    }

    /**
     * saveParams
     *
     * Saving module parameters
     *
     * @access public
     */
    function saveParams($data = 1)
    {
        $p = array();
        if (isset($this->id)) {
            $p["id"] = $this->id;
        }
        if (isset($this->view_mode)) {
            $p["view_mode"] = $this->view_mode;
        }
        if (isset($this->edit_mode)) {
            $p["edit_mode"] = $this->edit_mode;
        }
        if (isset($this->tab)) {
            $p["tab"] = $this->tab;
        }
        return parent::saveParams($p);
    }

    /**
     * getParams
     *
     * Getting module parameters from query string
     *
     * @access public
     */
    function getParams()
    {
        global $id;
        global $mode;
        global $view_mode;
        global $edit_mode;
        global $tab;
        if (isset($id)) {
            $this->id = $id;
        }
        if (isset($mode)) {
            $this->mode = $mode;
        }
        if (isset($view_mode)) {
            $this->view_mode = $view_mode;
        }
        if (isset($edit_mode)) {
            $this->edit_mode = $edit_mode;
        }
        if (isset($tab)) {
            $this->tab = $tab;
        }
    }

    /**
     * Run
     *
     * Description
     *
     * @access public
     */
    function run()
    {
        global $session;
        $out = array();
        if ($this->action == 'admin') {
            $this->admin($out);
        } else {
            $this->usual($out);
        }
        if (isset($this->owner->action)) {
            $out['PARENT_ACTION'] = $this->owner->action;
        }
        if (isset($this->owner->name)) {
            $out['PARENT_NAME'] = $this->owner->name;
        }
        $out['VIEW_MODE'] = $this->view_mode;
        $out['EDIT_MODE'] = $this->edit_mode;
        $out['MODE'] = $this->mode;
        $out['ACTION'] = $this->action;
        $this->data = $out;
        $p = new parser(DIR_TEMPLATES . $this->name . "/" . $this->name . ".html", $this->data, $this);
        $this->result = $p->result;
    }

    /**
     * BackEnd
     *
     * Module backend
     *
     * @access public
     */
    function admin(&$out)
    {

        $out['OK_MSG'] = gr('ok_msg');
        $out['ERR_MSG'] = gr('err_msg');

        $this->getConfig();
        $out['NETWORK_KEY'] = $this->config['NETWORK_KEY'];

        if (!$this->checkIdentity() || !file_exists(ROOT . 'cms/zerotier/identity.txt')) {
            $out['WRONG_IDENTITY'] = 1;
        }

        if ($this->mode == 'reset_identity') {
            $this->resetIdentity();
            $this->redirect("?ok_msg=" . urlencode("Identity reseted"));
        }

        if ($this->view_mode == 'update_settings') {
            $this->config['NETWORK_KEY'] = gr('network_key');
            $this->saveConfig();
            $this->redirect("?");
        }

        if ($this->mode == 'join') {
            safe_exec('sudo zerotier-cli join ' . $this->config['NETWORK_KEY']);
            $this->redirect("?ok_msg=" . urlencode("Trying to join..."));
        }

        if ($this->mode == 'leave') {
            safe_exec('sudo zerotier-cli leave ' . $this->config['NETWORK_KEY']);
            $this->redirect("?ok_msg=" . urlencode("Leaving..."));
        }

        if ($this->mode == 'install') {
            safe_exec('curl -s https://install.zerotier.com | sudo bash');
            $this->redirect("?ok_msg=" . urlencode("Installing... Please wait it to be working in Zerotier status window."));
        }

    }

    function getZerotierConfigDir() {
        $dirs = array(
            '/var/lib/zerotier-one',
            '/var/db/zerotier-one',
            '/Library/Application Support/ZeroTier/One',
            'C:\\ProgramData\\ZeroTier\\One'
        );
        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                return $dir;
            }
        }
        return false;
    }

    function checkIdentity()
    {
        $serial = getSystemSerial();
        if (file_exists(ROOT . 'cms/zerotier/identity.txt')) {
            $saved_identity = LoadFile(ROOT . 'cms/zerotier/identity.txt');
            if ($serial != $saved_identity) {
                return false;
            }
        }
        return true;
    }

    function resetIdentity()
    {
        DebMes("Resetting identity", 'zerotier');
        $serial = getSystemSerial();
        if (!is_dir(ROOT . 'cms/zerotier')) {
            mkdir(ROOT . 'cms/zerotier', 0777, true);
        }
        SaveFile(ROOT . 'cms/zerotier/identity.txt', $serial);

        $zerotier_config_dir = $this->getZerotierConfigDir();
        if ($zerotier_config_dir) {
            exec('sudo service zerotier-one stop');
            sleep(1);
            exec('sudo rm '.$zerotier_config_dir.'/identity.*');
            sleep(1);
            exec('sudo service zerotier-one start');
        }

    }

    /**
     * FrontEnd
     *
     * Module frontend
     *
     * @access public
     */
    function usual(&$out)
    {
        if ($this->ajax) {
            $op = gr('op');
            if ($op == 'zerotier_status') {
                $res = exec('sudo zerotier-cli status', $ret);
                $data = implode("\n", $ret);
                $data = preg_replace("/(\d+\.\d+\.\d+\.\d+)/uis", '<b>$1</b>', $data);
                if ($data == '') {
                    $data = 'Not installed yet :(';
                }
                echo "<pre>" . $data . "</pre>";
            }
            if ($op == 'network_status') {
                $res = exec('ifconfig', $ret);
                $data = implode("\n", $ret);
                $data = preg_replace("/(\d+\.\d+\.\d+\.\d+)/uis", '<b>$1</b>', $data);
                echo "<pre>System time: " . date('Y-m-d H:i:s') . "\n\n" . $data . "</pre>";
            }
            exit;
        }
        $this->admin($out);
    }

    /**
     * Install
     *
     * Module installation routine
     *
     * @access private
     */
    function install($data = '')
    {
        $identity_file = ROOT . 'cms/zerotier/identity.txt';
        if (!file_exists($identity_file)) {
            $serial = getSystemSerial();
            mkdir(ROOT . 'cms/zerotier', 0777, true);
            SaveFile(ROOT . 'cms/zerotier/identity.txt', $serial);
        } elseif (!$this->checkIdentity()) {
            $this->resetIdentity();
        }
        parent::install();
    }
// --------------------------------------------------------------------
}
/*
*
* TW9kdWxlIGNyZWF0ZWQgQXVnIDAxLCAyMDIwIHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/
