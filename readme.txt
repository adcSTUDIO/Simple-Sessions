=== Simple Sessions for WordPress ===
Contributors: Kevin Newman, ericmann
Tags: session
Requires at least: 3.4.2
Tested up to: 3.8
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Developer friendly session manager for WordPress.

== Description ==

Adds a developer friendly, partitionable, persistent session manager for use in WordPress. Session data is stored in the WordPress Options table. Transients are no good for the purposes of session data, because data is not guaranteed to be available if a cache (such as MemCached on WP Engine) is used to replace the default transient backing store in wp_options.

Simple Sessions provides plugin and theme authors the ability to use WordPress-managed session variables without having to use the standard PHP `$_SESSION` superglobal.

Session partitioning allows a developer to isolate their own session data.

Delayed initialization provides opportunity to make sure serialized classes are available before unserialization occurs.

NOTE: You must construct your session instance before any headers have been sent to the client, since the constructor will send cookie headers.

Simple Sessions is a fork of WP-Session-Manager, by Eric Mann.

== Installation ==

= Manual Installation =

1. Upload the entire `/wp-simple-sessions` folder to the plugins directory (usually `/wp-content/plugins/`) directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Use `WP_SimpleSession::get_instance($partition_id)` in your code.

== Frequently Asked Questions ==

= How do I add session variables? =

First, make a reference to the SimpleSession instance.  Then, use the accessors to get and set values:

`$my_session = SimpleSession::factory('user_session');

// set values
$my_session->set('user_name', 'User Name');                             // A string
$my_session->set('user_contact', array( 'email' => 'user@name.com' ) ); // An array
$my_session->set('user_obj', new WP_User( 1 ) );                        // An object

// get by key later
echo $my_session->get('user_name');

// or get a reference to the storage array
print_r( $my_session->get() ); // returns everything as an array`

= How long do session variables live? =

By default, session variables will live for 24 minutes from the last time they were accessed - either read or write.

This value can be changed by using the `wp_session_expiration` filter:

`add_filter( 'wp_session_expiration', function() { return 60 * 60; } ); // Set expiration to 1 hour`

= What does partionable mean? =

You can have multiple sessions at the same time, each with their own cookie. If you are a plugin or theme developer, you can keep your session data separate from anyone else's. Just pass a unique name to the factory.

= Does the session automatically start? =

Yes and no. There is no global automatic session (use WP Session Manager for that). But SimpleSession does set itself up when you instantiate it using the factory method, or by directly instantiating SimpleSession (recommended to use the factory).

= Why did you fork WP Session Manager? =

Two reasons, one practical and one subjective.

1. I needed to start the session later than WP Session Manager allows. Some of my class files in the theme must be serialized into the session data store are not available when the plugin runs, so WP Session Manager was not restoring my data correctly.

2. I don't like the Class based accessor system thath WP Session Manager uses. I'd rather use set/get methods and/or have direct access to the storage array. This is more like dealing with a regular PHP array.

== Screenshots ==

None

== Changelog ==

= 1.0 =
* Initial fork from WP Session Manager (v1.1) - many major chnages. Created SimpleSession.
