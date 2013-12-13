<?php
/**
 * Simple WordPress session managment.
 *
 * @package WordPress
 * @subpackage Session
 * @since   3.7.0
 */

/**
 * WordPress Session class for managing user session data.
 *
 * @package WordPress
 * @since   3.7.0
 */
class SimpleSession {
	/**
	 * Session Name.
	 *
	 * @var string
	 */
	protected $session_name;

	/**
	 * Unique ID of the current session.
	 *
	 * @var string
	 */
	protected $session_id;

	/**
	 * Unix timestamp when session expires.
	 *
	 * @var int
	 */
	protected $expires;

	/**
	 * Unix timestamp indicating when the expiration time needs to be reset.
	 *
	 * @var int
	 */
	protected $exp_variant;

	protected $container;

	protected $opt_key;
	protected $exp_key;

	protected $dirty;

	protected static $instances = array();

	/**
	 * Makes and gets a session instance.
	 *
	 * @param bool $session_id Session ID from which to populate data.
	 *
	 * @return bool|SimpleSession
	 */
	public static function factory( array $config = array() )
	{
		if ( array_key_exists( 'key', $config ) ) {
			$key = $config['key'];
			if ( ! array_key_exists( $key, self::$instances ) ) {
				self::$instances[ $key ] = new self( $key );
			}
			return self::$instances[ $key ];
		}

		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function set( $key, $value )
	{
		$this->dirty = true;
		if ( is_array( $key ) )
			$this->container = array_merge( $key, $this->container);
		else
			$this->container[ $key ] = $value;
	}

	public function get( $key = NULL )
	{
		if ( is_null( $key ) )
			return $this->container;

		if ( ! array_key_exists( $key, $this->container ) )
			return null;
		else
			return $this->container[ $key ];
	}

	/**
	 * Default constructor.
	 * Will rebuild the session collection from the given session ID if it exists. Otherwise, will
	 * create a new session with that ID.
	 *
	 * @param $session_id
	 * @uses apply_filters Calls `wp_session_expiration` to determine how long until sessions expire.
	 */
	protected function __construct( $session_name = 'simple' )
	{
		$this->session_name = $session_name;

		if ( isset( $_COOKIE[ $session_name ] ) ) {
			$cookie = stripslashes( $_COOKIE[ $session_name ] );
			$cookie_crumbs = explode( '||', $cookie );

			$this->session_id = $cookie_crumbs[0];
			$this->expires = $cookie_crumbs[1];
			$this->exp_variant = $cookie_crumbs[2];

			$this->make_opt_names();

			// Update the session expiration if we're past the variant time
			if ( time() > $this->exp_variant ) {
				$this->set_expiration();
				update_option( $this->exp_opt, $this->expires );
			}
		} else {
			$this->session_id = $this->generate_id();
			$this->make_opt_names();
			$this->set_expiration();
		}

		// save your work at the end of the day
		add_action( 'shutdown', array( &$this, 'write_data' ) );

		$this->read_data();

		$this->set_cookie();
	}

	/**
	 * Set both the expiration time and the expiration variant.
	 *
	 * If the current time is below the variant, we don't update the session's expiration time. If it's
	 * greater than the variant, then we update the expiration time in the database.  This prevents
	 * writing to the database on every page load for active sessions and only updates the expiration
	 * time if we're nearing when the session actually expires.
	 *
	 * By default, the expiration time is set to 30 minutes.
	 * By default, the expiration variant is set to 24 minutes.
	 *
	 * As a result, the session expiration time - at a maximum - will only be written to the database once
	 * every 24 minutes.  After 30 minutes, the session will have been expired. No cookie will be sent by
	 * the browser, and the old session will be queued for deletion by the garbage collector.
	 *
	 * @uses apply_filters Calls `wp_session_expiration_variant` to get the max update window for session data.
	 * @uses apply_filters Calls `wp_session_expiration` to get the standard expiration time for sessions.
	 */
	protected function set_expiration()
	{
		$this->exp_variant = time() + (int) apply_filters( 'wp_session_expiration_variant', 24 * 60 );
		$this->expires = time() + (int) apply_filters( 'wp_session_expiration', 30 * 60 );
	}

	/**
	 * Set the session cookie
	 */
	protected function set_cookie()
	{
		setcookie( $this->session_name, $this->session_id . '||' . $this->expires . '||' . $this->exp_variant , $this->expires, COOKIEPATH, COOKIE_DOMAIN );
	}

	/**
	 * Generate a cryptographically strong unique ID for the session token.
	 *
	 * @return string
	 */
	protected function generate_id()
	{
		if ( ! class_exists('PasswordHash') )
			require_once( ABSPATH . 'wp-includes/class-phpass.php');

		$hasher = new PasswordHash( 8, false );

		return md5( $hasher->get_random_bytes( 32 ) );
	}

	/**
	 * Read data from a transient for the current session.
	 *
	 * Automatically resets the expiration time for the session transient to some time in the future.
	 *
	 * @return array
	 */
	protected function read_data()
	{
		$this->container = get_option( $this->opt_key, array() );
		$this->dirty = false;
		return $this->container;
	}

	/**
	 * Write the data from the current session to the data storage system.
	 */
	public function write_data()
	{
		// Avoid excessive database reads/writes if nothing changed.
		if ( ! $this->dirty ) return;

		if ( false === get_option( $this->opt_key ) ) {
			add_option( $this->opt_key, $this->container, '', 'no' );
			add_option( $this->exp_key, $this->expires, '', 'no' );
		} else {
			update_option( $this->opt_key, $this->container );
		}
	}

	/**
	 * Regenerate the current session's ID.
	 *
	 * @param bool $delete_old Flag whether or not to delete the old session data from the server.
	 */
	public function regenerate_id( $delete_old = false )
	{
		if ( $delete_old ) {
			delete_option( $this->opt_key);
		}

		$this->session_id = $this->generate_id();

		$this->set_cookie();
	}

	/**
	 * Check if a session has been initialized.
	 *
	 * @return bool
	 */
	public function session_started() {
		throw new Exception("Not Implemented");
	}

	/**
	 * Return the read-only cache expiration value.
	 *
	 * @return int
	 */
	public function cache_expiration() {
		return $this->expires;
	}

	/**
	 * Flushes all session variables.
	 */
	public function reset() {
		$this->container = array();
	}

	private function make_opt_names()
	{
			$this->opt_key = 'smplsess|'.$this->session_name.'|'.$this->session_id;
			$this->exp_key = 'smplsess_expires|'.$this->session_name.'|'.$this->session_id;
	}

}
