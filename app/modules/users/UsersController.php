<?php

class UsersController {

	private $actions;


	/** ----------------------------------------------------------------------------
	 * Constructor
	 */

	public function __construct(DependencyContainer $dependencies) {
		$dependencies->register($this);

		require 'UsersActions.php';
		$this->actions = new UsersActions($dependencies);
	}


	/** ----------------------------------------------------------------------------
	 * Login
	 */

	public function login() {
		$login_status = $this->_auth->login(
			$_POST['email'] ?? null,
			$_POST['password'] ?? null
		);
		$this->_rest->set('status', $login_status);
	}


	/** ----------------------------------------------------------------------------
	 * Logout
	 */

	public function logout() {
		$logout_status = $this->_auth->logout();
		$this->_rest->set('status', $logout_status);
	}


	/** ----------------------------------------------------------------------------
	 * List
	 */

	public function get_list() {
		$users_list = $this->actions->get_list();
		$this->_rest_store->set('data', $users_list);
	}


	/** ----------------------------------------------------------------------------
	 * Create user
	 */

	public function create() {
		if (empty($_GET['email'])) {
			throw new Exception("Email not provided");
		}

		if (empty($_GET['password'])) {
			throw new Exception("Password not provided");
		}

		if (!$this->_auth->email_validate($_GET['email'])) {
			throw new Exception("Provided email address is incorrect");
		}

		if (!$this->_auth->password_validate($_GET['password'])) {
			throw new Exception("Provided password does not meet the requirements");
		}

		$result = $this->actions->create_user(
			$_GET['email'],
			$this->_auth->password_encode($_GET['password']),
			Auth::LVL_ADMIN
		);

		if (!$result) {
			throw new Exception("Unknown error occured while adding new user");
		}
		else {
			$this->_rest->set('status', true);
		}
	}


	/** ----------------------------------------------------------------------------
	 * Remove user
	 */

	public function remove() {
		$this->require_auth(Auth::LVL_ADMIN);
		/** @todo */
	}
}