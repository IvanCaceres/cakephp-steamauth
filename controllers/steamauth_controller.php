<?php

App::import('Services', 'Steamauth.Steamauth');


class SteamAuthController extends SteamAuthAppController {
	var $uses = array();
	var $name = 'Steamauth';

	var $components = array();
    /**
     * Redirect the user to the Steam authentication page
     *
     *
     */

   	public function __construct()
    {
        parent::__construct();
		$this->steam = new SteamAuth();
    }

    public function redirectToSteam()
    {
    	// $steam = new Steamauth;
    	// echo 'dude yo yo dude!!!';
    	$this->redirect($this->steam->getAuthUrl());
    	// exit;
        // return $this->steam->redirect();
    }

    /**
     * Get Steam user info and log in
     *
     */
    public function handle()
    {
        $this->steam->params = $this->params['url'];
        if ($this->steam->validate()) {
            $info = $this->steam->getUserInfo();

            debug(array("show info:", $info));

            // if (!is_null($info)) {
            //     $user = $this->findOrNewUser($info);

            //     Auth::login($user, true);

            //     return redirect($this->redirectURL); // redirect to site
            // }
        }
        // return $this->redirectToSteam();
    }
}