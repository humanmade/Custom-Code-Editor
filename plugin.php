<?php
/**
 * Plugin Name: Custom Code Editor
 * Plugin URI: https://github.com/humanmade/Custom-Code-Editor
 * Description: Lets you add custom code snippets on a global, per-page or dependency basis. Requires Human Made's Custom Meta Boxes for per-page and dependency features.
 * Version: 1.0.1
 * License: GPL-2.0+
 * Requires PHP: 5.4
 * Author: Human Made Limited
 * Author URI: http://hmn.md
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @package CustomCodeEditor
 */

namespace CustomCodeEditor;

/**
 * @todo:: i18n
 *
 */

/**
 * Basefile definition for enqueing assets.
 */
const BASEFILE = __FILE__;

require __DIR__ . '/inc/namespace.php';
require __DIR__ . '/inc/cmb2-post-autocomplete.php';
require __DIR__ . '/inc/post-types.php';
require __DIR__ . '/inc/editor.php';
require __DIR__ . '/inc/frontend.php';

load();
Post_Types\load();
Editor\load();
Frontend\load();
CMB2_Post_Autocomplete\load();
