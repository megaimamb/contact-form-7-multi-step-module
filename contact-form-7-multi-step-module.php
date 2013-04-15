<?php
/*
Plugin Name: Contact Form 7 Multi-Step Forms
Plugin URI: http://www.mymonkeydo.com/contact-form-7-multi-step-module/
Description: Enables the Contact Form 7 plugin to create multi-page, multi-step forms.
Author: Webhead LLC.
Author URI: http://webheadcoder.com 
Version: 0.9
*/
/*  Copyright 2012 Webhead LLC (email: info at webheadcoder.com)

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	
	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/


if (!in_array('contact-form-7-modules/hidden.php', get_option( 'active_plugins', array() ))) {
	require_once(plugin_dir_path(__FILE__) . 'module-hidden.php');
}
require_once(plugin_dir_path(__FILE__) . 'module-session.php');
    
/**
 * init_sessions()
 *
 * @uses session_id()
 * @uses session_start()
 */
function wh_init_sessions() {
    if (!session_id()) {
        session_start();
    }
}
add_action('init', 'wh_init_sessions'); 
 
/**
 * Hide the second step of a form.  looks at hidden field 'step'.
 * Always show if the form is the first step.
 * If it's not the first step, make sure it's the next form in the steps.
 */
function wh_hide_cf7_step_2($cf7) {
    $formstring = $cf7->form;
    //check if form has a step field
    if (!is_admin() && preg_match('/\[hidden step "(\d+)-(\d+)"\]/', $formstring, $matches)) {
        if (!isset($matches[1]) || ($matches[1] != 1 && !isset($_SESSION['step'])) || ($matches[1] != 1 && ((int) $_SESSION['step']) + 1 != $matches[1])) {
            $cf7->form = apply_filters('wh_hide_cf7_step_message', "Please fill out the form on the previous page");
        }
        if (count($matches) == 3 && $matches[1] != $matches[2]) {
			add_filter('wpcf7_ajax_json_echo', 'wh_clear_success_message', 10, 2);
        }
    }
    return $cf7;
}
add_action('wpcf7_contact_form', 'wh_hide_cf7_step_2');

/**
 * Handle a multi-step cf7 form.
 */
function wh_store_data_steps(&$cf7) {
    if (isset($cf7->posted_data['step'])) {
        if (preg_match('/(\d+)-(\d+)/', $cf7->posted_data['step'], $matches)) {
            $curr_step = $matches[1];
            $last_step = $matches[2];
        }       
		$prev_data = isset($_SESSION['cf7_posted_data'])?$_SESSION['cf7_posted_data']:array();
		//remove empty [form] tags from posted_data so $prev_data can be stored.
		$fes = wpcf7_scan_shortcode();
		foreach ( $fes as $fe ) {
			if ( empty( $fe['name'] ) || $fe['type'] != 'form' )
				continue;
			unset($cf7->posted_data[$fe['name']]);
		}
		if ($curr_step != $last_step) {
			$cf7->skip_mail = true;
			$_SESSION['step'] = $curr_step;
		    $_SESSION['cf7_posted_data'] = array_merge($prev_data, $cf7->posted_data);
		}
		else {
			$cf7->posted_data = array_merge($prev_data, $cf7->posted_data);
			unset($_SESSION['step']);
			unset($_SESSION['cf7_posted_data']);
		}
	}
}

add_action( 'wpcf7_before_send_mail', 'wh_store_data_steps', 9 );

/**
 * Hide success message if form is redirecting to another page.
 */
function wh_clear_success_message($items, $result) {
    remove_filter('wpcf7_ajax_json_echo', 'wh_clear_success_message');
    if ($items['mailSent'] && isset($items['onSentOk']) && count($items['onSentOk']) > 0) {
        $items['onSentOk'][] = "$('" . $items['into'] . "').find('div.wpcf7-response-output').css('opacity',0);";
    }
    return $items;
}


/************************************************************************************************************
 * Contact Form 7 has a nice success message after submitting its forms, but on a multi-step form,
 * this can cause confusion if it shows and the page immediately leaves to the next page.
 * The functions below hide the success messages on multi-step forms.
************************************************************************************************************/

/**
 * Hide form when done.
 */
function wh_hide_multistep_form($items, $result) {
    remove_filter('wpcf7_ajax_json_echo', 'wh_hide_multistep_form');
    if ($items['mailSent'] && !isset($items['onSentOk'])) {
        $items['onSentOk'] = array("$('" . $items['into'] . " form').children().not('div.wpcf7-response-output').hide();");
    }
    return $items;
}

/**
 * Add filter to clear form if this is a multistep form.
 */
function wh_cf7_before_mail($cf7) {
    if (isset($_SESSION['step'])) {
        add_filter('wpcf7_ajax_json_echo', 'wh_hide_multistep_form', 10, 2);
    }
}
add_action( 'wpcf7_before_send_mail', 'wh_cf7_before_mail', 8 );

