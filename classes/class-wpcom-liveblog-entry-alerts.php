<?php

/**
 * Class WPCOM_Liveblog_Entry_Alerts
 *
 * Adds /alert command allowing the author
 * to send users a html5 notification.
 */
class WPCOM_Liveblog_Entry_Alerts {

    /**
     * Called by WPCOM_Liveblog::load(),
     * it attaches the new command.
     */
    public static function load() {
        add_filter( 'liveblog_active_commands',  array( __CLASS__, 'add_alert_command' ), 10 );
    }

    /**
     * Adds the /alert command and sets both
     * filter and action to false as js
     * will target class.
     *
     * @param $commands
     * @return mixed
     */
    public static function add_alert_command( $commands ) {
        $commands['alert'] = false;
        return $commands;
    }
}