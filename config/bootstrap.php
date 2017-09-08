<?php

	Configure::write('steam_auth', array(
		/*
		* Redirect URL after login
		*/
		'redirect_url' => '/steam/redirect',
		'https' => false
	));