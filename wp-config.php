<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          '+^jeZ<r_Ui&>7M8ER%/?/;]#8mjJo)p2y:]VZqjeVP,Blz2h,cf9U2R$>(Y.17[B' );
define( 'SECURE_AUTH_KEY',   'b4/`+*ca@goO+6[t/%X-iE4>T,!8(y-]j)j)8^wN[9rJ`a$d-67*|$VsJ//V9eiU' );
define( 'LOGGED_IN_KEY',     '-tdBKPJR}{O&[4 =#P#y#_B-iU,lwbonJKaCTH>*Jy+|y>J.L%3_/0<`?B+Ib3J-' );
define( 'NONCE_KEY',         '>lz!F,XK-Sw2 jQmO4Q~u>r?py@0G v>J=ZX=-72L8Y_s~^u+A)=v;.eCwY*UWw(' );
define( 'AUTH_SALT',         '_f2_q$`L*n&]HqUNGVE:`)A@wKW(1i 7Z0]x2UI<ZEg_Y[48t(%C7zd*)U vB=3(' );
define( 'SECURE_AUTH_SALT',  'Vg6+9F+B_9.%B76}vGu1YVG?7u[Boz^^g4_;xi?3JXntj8z6P#b6Z^;PSCrI{ m~' );
define( 'LOGGED_IN_SALT',    '/0X^Dyrco%L.^_`GBvQh^| hH>VrBZY~aPi]PlX.gzfsw4`zO2}[bTp,ER)j+]A.' );
define( 'NONCE_SALT',        '0]<yUu3t0]i~AX7f3Ubb&J90 7HaPLyZm}/m;},5./+4]m2C5l:=~)H@>N<(m|5y' );
define( 'WP_CACHE_KEY_SALT', 'p>AtwY.468!`40!;=S%O>&8cf@qF8:@mgV}% {lm=|ULaepO$${MP80,L51WTP68' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', true);

/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'WP_ENVIRONMENT_TYPE', 'local' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
