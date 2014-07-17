<?php
	/*
	 * Plugin Name: Studiorum User Groups Automatic Assignment
	 * Description: An add-on for Studiorum User Groups which allows the admin to automatically create groups and assign users
	 * Version:     0.1
	 * Plugin URI:  #
	 * Author:      UBC, CTLT, Richard Tape
	 * Author URI:  http://ubc.ca/
	 * Text Domain: studiorum-user-groups-automatic
	 * License:     GPL v2 or later
	 * Domain Path: languages
	 *
	 * studiorum-user-groups-automatic is free software: you can redistribute it and/or modify
	 * it under the terms of the GNU General Public License as published by
	 * the Free Software Foundation, either version 2 of the License, or
	 * any later version.
	 *
	 * studiorum-user-groups-automatic is distributed in the hope that it will be useful,
	 * but WITHOUT ANY WARRANTY; without even the implied warranty of
	 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	 * GNU General Public License for more details.
	 *
	 * You should have received a copy of the GNU General Public License
	 * along with studiorum-user-groups-automatic. If not, see <http://www.gnu.org/licenses/>.
	 *
	 * @package User Groups Automatic
	 * @category Core
	 * @author Richard Tape
	 * @version 0.1.0
	 */

	if( !defined( 'ABSPATH' ) ){
		die( '-1' );
	}

	if( !defined( 'STUDIORUM_USER_GROUPS_AUTOMATIC_DIR' ) ){
		define( 'STUDIORUM_USER_GROUPS_AUTOMATIC_DIR', plugin_dir_path( __FILE__ ) );
	}

	if( !defined( 'STUDIORUM_USER_GROUPS_AUTOMATIC_URL' ) ){
		define( 'STUDIORUM_USER_GROUPS_AUTOMATIC_URL', plugin_dir_url( __FILE__ ) );
	}


	class Studiorum_User_Groups_Automatic
	{

		// The option name stored in the db
		var $optionName = 'studiorum_user_groups';

		// Validated input
		var $validatedInput = array();

		// Number of users on this site
		var $numberOfUsers = false;

		/**
		 * Actions and filters
		 *
		 * @since 0.1
		 *
		 * @param null
		 * @return null
		 */

		public function __construct()
		{

			// We have an action after the Add User Groups form. Let's hook in and add our own form
			add_action( 'studiorum_user_groups_add_new_group_after_form', array( $this, 'studiorum_user_groups_add_new_group_after_form__addAutomaticAssignmentButtons' ) );

			// When a submission happens, a custom action is fired. Let's hook in and do stuff
			add_action( 'studiorum_user_groups_automatic_assignment', array( $this, 'studiorum_user_groups_automatic_assignment__calculateAndAssignGroups' ) );

			// Handle when random is pressed
			add_action( 'studiorum_user_groups_automatic_assignment_type_random', array( $this, 'studiorum_user_groups_automatic_assignment_type_random' ) );

			// Handle when random is pressed and details are added
			add_action( 'studiorum_user_groups_automatic_assignment_handle_random_fields', array( $this, 'studiorum_user_groups_automatic_assignment_handle_random_fields' ) );

			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts__loadJS' ), 20 );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts__loadCSS' ) );

		}/* __construct() */


		/**
		 * Add our markup for the form with buttons for the automatic assignment of groups
		 *
		 * @since 0.1
		 *
		 * @param null
		 * @return null
		 */

		public function studiorum_user_groups_add_new_group_after_form__addAutomaticAssignmentButtons()
		{

			// By default, we only want to do this if there are currently no user groups.
			if( $this->userGroupsExist() && !apply_filters( 'studiorum_user_groups_automatic_show_buttons_when_user_groups_exist', false ) ){
				return;
			}

			// var_dump( $_REQUEST );

			if( isset( $_REQUEST['automatic-group-assignment'] ) && $_REQUEST['automatic-group-assignment'] == 'true' ){
				do_action( 'studiorum_user_groups_automatic_assignment' );
			}

			if( isset( $_REQUEST['automatic-group-assignment-random-group'] ) && $_REQUEST['automatic-group-assignment-random-group'] == 'true' ){
				do_action( 'studiorum_user_groups_automatic_assignment_handle_random_fields' );
			}

			?>

			<h3 id="auto-user-group-assignment-title"><?php _e( 'Automatic User Group Assignment', 'studiorum-user-groups-automatic' ); ?></h3>

			<form id="automatic-group-assignment" method="post" action="" class="validate">

				<?php wp_nonce_field( 'automatically-add-groups', '_wpnonce_automatically-add-groups' ); ?>

				<?php do_action( 'studiorum_user_groups_automatic_add_new_group_form_start' ); ?>

				<input type="hidden" name="automatic-group-assignment" value="true" />

				<input type="submit" name="automatic-group-type" id="automatic-group-random" class="button button-secondary" value="<?php _e( 'Random', 'studiorum-user-groups-automatic' ); ?>" />
				<!-- <input type="submit" name="automatic-group-type" id="automatic-group-alphabetical" class="button button-secondary" value="<?php _e( 'Alphabetical', 'studiorum-user-groups-automatic' ); ?>" /> -->

			</form>


			<?php

		}/* studiorum_user_groups_add_new_group_after_form__addAutomaticAssignmentButtons() */


		/**
		 * When one of the group assignment buttons is pressed we need to calculate what to do and then add the groups as necessary
		 *
		 * @since 0.1
		 *
		 * @param null
		 * @return null
		 */

		public function studiorum_user_groups_automatic_assignment__calculateAndAssignGroups()
		{

			if( !$this->isValidInput() ){
				return false;
			}

			$groupAssignmentType = $this->getGroupAssignmentType();

			if( !$groupAssignmentType ){
				return false;
			}

			$type = strtolower( $groupAssignmentType );

			do_action( 'studiorum_user_groups_automatic_assignment_type_' . $type, $this->validatedInput );

		}/* studiorum_user_groups_automatic_assignment__calculateAndAssignGroups() */


		/**
		 * When the random button is pressed, we need to show some extra fields to show number of groups
		 * and/or how many per group and fields to decide what to do with odd-ones-out
		 *
		 * @since 0.1
		 *
		 * @param null
		 * @return null
		 */

		public function studiorum_user_groups_automatic_assignment_type_random()
		{

			// In order to help out we'll let the user know how many users there are
			$numberOfUsers = $this->numberOfUsers();

			$message = 'There are ' . $numberOfUsers . ' registered on this site.';
			$message = apply_filters( 'studiorum_user_groups_automatic_random_num_users_message', __( $message, 'studiorum-user-groups-automatic' ), $numberOfUsers );

			?>

			<h3><?php _e( 'Random User Group Assignment', 'studiorum-user-groups-automatic' ); ?></h3>

			<p><?php echo $message; ?></p>

			<form id="automatic-group-assignment-random" method="post" action="" class="validate">

				<?php wp_nonce_field( 'add-random-user-group', '_wpnonce_add-random-user-group' ); ?>

				<?php do_action( 'studiorum_user_groups_automatic_random_form_start' ); ?>

				<input type="hidden" name="automatic-group-assignment-random-group" value="true" />

				<div class="form-field-container">

					<div class="groups-and-users-container">

						<div class="form-field form-required random-num-groups">
							<label for="random-num-groups"><?php _ex( 'Number of Groups', 'Number of groups Title' ); ?></label>
							<input name="random-num-groups" id="random-num-groups" type="text" value="" size="40" aria-required="true" />
						</div>

						<div class="form-field form-required random-num-users-per-group">
							<label for="random-num-users-per-group"><?php _ex( 'Number of Users per Group', 'Number of users per group Title' ); ?></label>
							<input name="random-num-users-per-group" id="random-num-users-per-group" type="text" value="" size="40" aria-required="true" />
						</div>

						<div class="error-message-container">&nbsp;</div>

					</div><!-- .groups-and-users-container -->

					<div class="checkbox-field handle-outliers-random-fields">
						<p class="outliers-text">
							<?php _e( 'There will be <span class="numOutliers">&nbsp;</span> outlier(s), how should they be handled?', 'studiorum-user-groups-automatic' ); ?>
						</p>
						<label for="handle-outliers-random">
							<input type="radio" name="handle-outliers" id="handle-outliers-random" value="handle-outliers-random" />
							<?php _ex( 'Random assignment', 'Random Assignment Label' ); ?>
						</label>

						<label for="handle-outliers-manual">
							<input type="radio" name="handle-outliers" id="handle-outliers-manual" value="handle-outliers-manual" />
							<?php _ex( 'Manual assignment', 'Manual Assignment Label' ); ?>
						</label>

					</div>

				</div><!-- .form-field-container -->

				<?php submit_button( __( 'Create Random Groups', 'studiorum-user-groups-automatic' ) ); ?>

			</form>

			<?php

		}/* studiorum_user_groups_automatic_assignment_type_random() */


		/**
		 * After random is pressed we display some more fields. After that form is submitted we get here
		 * Validate the input and then make the groups as necessary
		 *
		 * @since 0.1
		 *
		 * @param null
		 * @return null
		 */

		public function studiorum_user_groups_automatic_assignment_handle_random_fields()
		{
			$validatedInput = $this->validateInput( $_REQUEST, 'random-group' );

			$numberOfUsers = $this->numberOfUsers();

			// if we have 'random-num-groups' set, then we create that many groups with users in randomly
			$numOfGroupsToCreate = ( isset( $validatedInput['random-num-groups'] ) && !empty( $validatedInput['random-num-groups'] ) ) ? $validatedInput['random-num-groups'] : false;

			// if we have 'random-num-users-per-group' set, then we create the correct amount of groups that can contain that many users
			$numOfUsersInEachGroup = ( isset( $validatedInput['random-num-users-per-group'] ) && !empty( $validatedInput['random-num-users-per-group'] ) ) ? $validatedInput['random-num-users-per-group'] : false;

			$handleOutliers = ( isset( $validatedInput['handle-outliers'] ) && !empty( $validatedInput['handle-outliers'] ) ) ? $validatedInput['handle-outliers'] : false;

			// First check if there's both. If not, throw an error
			if( !$numOfGroupsToCreate || !$numOfUsersInEachGroup ){
				wp_die( 'Please provide both number of groups and number of users in each group' );
			}

			// Check for outliers
			$numOfOutliers = $numberOfUsers - ( $numOfGroupsToCreate * $numOfUsersInEachGroup );

			$this->addGroups( $numOfGroupsToCreate, $numOfUsersInEachGroup, $numOfOutliers, $handleOutliers );

		}/* studiorum_user_groups_automatic_assignment_handle_random_fields() */


		/**
		 * Go ahead and add our groups. We first just need to make sure we have the data in a correct format
		 *
		 * @since 0.1
		 *
		 * @param string $param description
		 * @return string|int returnDescription
		 */

		public function addGroups( $numOfGroupsToCreate = 1, $numOfUsersInEachGroup = 1, $numOfOutliers = 0, $outlierAssignment = false )
		{

			// We need to get a list of all user IDs on this site as an array and then split that into $numOfGroupsToCreate chunks
			global $localizationData, $Studiorum_User_Groups;
			$allUsers = $localizationData['userData'];

			// We start by shuffling that data, so we have a random group
			shuffle( $allUsers );

			// Now chunk it
			$groupsOfUsers = array_chunk( $allUsers, $numOfUsersInEachGroup );

			$outliers = false;

			// If we have outliers, we need to handle them separately
			if( $numOfOutliers > 0 )
			{

				$outliers = $groupsOfUsers[$numOfGroupsToCreate-1];

				// Pop off the last group
				$outliersGroup = array_pop( $groupsOfUsers );

				if( !$outlierAssignment || $outlierAssignment == '' ){
					$outlierAssignment = 'handle-outliers-random';
				}

			}

			// Now loop over each of these chunks and create a group with these users
			foreach( $groupsOfUsers as $key => $userGroup )
			{
				
				// We don't want no zero-based nonsense
				$groundOne = $key + 1;

				// The title for this group is simply Group # and the slug is group-#
				$title = 'Group ' . $groundOne;
				$slug = 'group-' . $groundOne;

				// We need an array of user IDs
				$usersToAddToGroup = array();

				foreach( $userGroup as $uKey => $userData )
				{
					$usersToAddToGroup[] = $userData['userID'];
				}

				// Now form our data to pass to addNewGroup
				$newDataToAdd = array(
					'title' => $title,
					'slug' => $slug,
					'users' => $usersToAddToGroup
				);

				// Add the group
				$Studiorum_User_Groups->addNewGroup( $newDataToAdd );

			}

			if( !$outliers ){
				return;
			}

			switch( $outlierAssignment )
			{

				case 'handle-outliers-manual':
					
					// Programming-wise we don't have to do anything here.
					// $todo: Does it make sense to tell the user which users are left? Auto fill the 'Users' box with
					// their details, perhaps?
					$this->displayUsersNotAddedToGroups( $outliersGroup );

					break;
				
				case 'handle-outliers-random':
				default:
					
					// $outliersGroup is an array of users that need to be randonly assigned to a group.
					$this->addUsersToRandomGroups( $outliersGroup );
					
					break;

			}

		}/* addGroups */


		/**
		 * Helper method to assign users randomly to groups
		 *
		 * @since 0.1
		 *
		 * @param array $outliersGroup An array of users to add randomly to groups
		 * @return null
		 */

		private function addUsersToRandomGroups( $outliersGroup = array() )
		{

			if( empty( $outliersGroup ) ){
				return;
			}

			global $Studiorum_User_Groups;

			// Fetch the existing groups
			$existingData = $Studiorum_User_Groups->getExistingData();

			if( !$existingData || !is_array( $existingData ) || empty( $existingData ) ){
				return;
			}

			// Shuffle the array so it's random, keeping the keys as it's an associative array
			$shuffleKeys = array_keys( $existingData );
			shuffle( $shuffleKeys );
			$newArray = array();
			foreach( $shuffleKeys as $key ){
			    $newArray[$key] = $existingData[$key];
			}

			$existingData = $newArray;

			$numOfOutliers = count( $outliersGroup );

			for( $i=0; $i < $numOfOutliers; $i++ )
			{ 

				// The User ID to add
				$userIDToAdd = $outliersGroup[$i]['userID'];

				// Which array we're adding it to
				$titleOfThisKey = $shuffleKeys[$i];

				// Add it
				$existingData[$titleOfThisKey]['users'][] = $userIDToAdd;

			}

			// Set the data - we're completely overwriting what's there as it's all merged
			$Studiorum_User_Groups->setData( $existingData );

		}/* addUsersToRandomGroups() */


		/**
		 * When we have manual assignment of outliers, display which users haven't been assigned
		 *
		 * @since 0.1
		 *
		 * @param array $outliersGroup An array of users not assigned
		 * @return string|int returnDescription
		 */

		private function displayUsersNotAddedToGroups( $outliersGroup = array() )
		{

			if( !$outliersGroup || !is_array( $outliersGroup ) || empty( $outliersGroup ) ){
				return;
			}

			// We just need the UserIDs. Start fresh.
			$userIDS = array();

			foreach( $outliersGroup as $key => $user ){
				$userIDs[] = $user['userID'];
			}

			$userIDString = implode( ',', $userIDs );

			// Let's add the outliers into the users box automatically, add a dummy title, focus the
			// title input and show a message to the user to tell them what is happening
			?>

			<script>
				var titleElem = document.getElementById( 'group-title' );
				titleElem.value = "Enter Group Title";
				titleElem.focus();

				var usersElem = document.getElementById( 'group-users' );
				usersElem.value = "<?php echo $userIDString; ?>";
			</script>

			<div id="auto-group-message" class="error-message-container showme"><?php _e( 'The users which were not automatically associated with a group have been placed into the users box above. Please provide a title and add that group.', 'studiorum-user-groups-automatic' ); ?></div>

			<?php

		}/* displayUsersNotAddedToGroups() */


		/**
		 * Helper method to validate the user input, check nonces etc
		 *
		 * @since 0.1
		 *
		 * @param array $request An array of details that we need to validate. Probably from $_REQUEST
		 * @return array|false An array of sanitized input
		 */

		private function validateInput( $request = false, $fields = 'initial' )
		{

			if( !$request ){
				return false;
			}

			switch( $fields )
			{

				case 'initial':
					$output = $this->validateInitialInput( $request );
					break;
			
				case 'random-group':
					$output = $this->validateDeletedFieldsInput( $request );
					break;

				default:
					$output = $this->validateInitialInput( $request );
					break;
			
			}

			$output = apply_filters( 'studiorum_user_groups_automatic_validated_input', $output );

			$this->validatedInput = $output;

			return $output;

		}/* validateInput() */


		/**
		 * Validate the initial input fields
		 *
		 * @since 0.1
		 *
		 * @param array $request the $_REQUEST sent through
		 * @return array|bool validated form fields or false
		 */
		private function validateInitialInput( $request )
		{

			// Check we have a nonce, that it's an expected string and that it's a valid nonce
			if( !isset( $request['_wpnonce_automatically-add-groups'] ) || sanitize_text_field( $request['_wpnonce_automatically-add-groups'] ) == '' || !wp_verify_nonce( sanitize_text_field( $request['_wpnonce_automatically-add-groups'] ), 'automatically-add-groups' ) ){
				return false;
			}

			// Check that we're doing a group assignment
			if( !isset( $request['automatic-group-assignment'] ) || sanitize_text_field( $request['automatic-group-assignment'] ) != 'true' ){
				return false;
			}

			// Check that we have a group type
			if( !isset( $request['automatic-group-type'] ) || sanitize_text_field( $request['automatic-group-type'] ) == '' ){
				return false;
			}

			$output = array();

			$output['_wpnonce_automatically-add-groups'] 	= sanitize_text_field( $request['_wpnonce_automatically-add-groups'] );
			$output['automatic-group-assignment'] 			= sanitize_text_field( $request['automatic-group-assignment'] );
			$output['automatic-group-type'] 				= sanitize_text_field( $request['automatic-group-type'] );

			return $output;

		}/* validateInitialInput() */


		/**
		 * Validate the random group creation fields
		 *
		 *
		 * @since 0.1
		 *
		 * @param string $param description
		 * @return string|int returnDescription
		 */

		private function validateDeletedFieldsInput( $request )
		{

			// Check we have a nonce, that it's an expected string and that it's a valid nonce
			if( !isset( $request['_wpnonce_add-random-user-group'] ) || sanitize_text_field( $request['_wpnonce_add-random-user-group'] ) == '' || !wp_verify_nonce( sanitize_text_field( $request['_wpnonce_add-random-user-group'] ), 'add-random-user-group' ) ){
				return false;
			}

			// Check that we're doing a random group assignment
			if( !isset( $request['automatic-group-assignment-random-group'] ) || sanitize_text_field( $request['automatic-group-assignment-random-group'] ) != 'true' ){
				return false;
			}

			$output = array();

			$output['random-num-groups'] 			= ( isset( $request['random-num-groups'] ) ) ? intval( sanitize_text_field( $request['random-num-groups'] ) ) : false;
			$output['random-num-users-per-group'] 	= ( isset( $request['random-num-users-per-group'] ) ) ? intval( sanitize_text_field( $request['random-num-users-per-group'] ) ) : false;
			$output['handle-outliers'] 				= ( isset( $request['handle-outliers'] ) ) ? sanitize_text_field( $request['handle-outliers'] ) : false;

			return $output;

		}/* validateDeletedFieldsInput() */


		/**
		 * Do we have valid input?
		 *
		 * @since 0.1
		 *
		 * @param null
		 * @return bool
		 */

		private function isValidInput( $fields = 'initial' )
		{

			$validatedInput = $this->validateInput( $_REQUEST, $fields );

			if( $validatedInput && is_array( $validatedInput ) && !empty( $validatedInput ) ){
				return true;
			}

			return false;

		}/* isValidInput() */


		/**
		 * Fetch what type of group assignment this is
		 *
		 * @since 0.1
		 *
		 * @param null
		 * @return string|false - The group type or false if not valid
		 */

		private function getGroupAssignmentType()
		{

			if( !isset( $this->validatedInput ) || !is_array( $this->validatedInput ) || empty( $this->validatedInput ) ){
				return false;
			}

			$validatedInput = $this->validatedInput;

			if( !isset( $validatedInput['automatic-group-type'] ) ){
				return false;
			}

			return $validatedInput['automatic-group-type'];

		}/* getGroupAssignmentType() */


		/**
		 * Helper method to fetch all current user groups
		 *
		 * @since 0.1
		 *
		 * @param null
		 * @return null
		 */

		private function getCurrentUserGroups()
		{

			$groups = get_option( $this->optionName, array() );

			return $groups;

		}/* getCurrentUserGroups() */


		/**
		 * Determine whether there are current user groups or not
		 *
		 * @since 0.1
		 *
		 * @param null
		 * @return bool
		 */

		private function userGroupsExist()
		{

			// Fetch from the option
			$groupsFromOption = $this->getCurrentUserGroups();

			// If we have nothing, or there's an empty array, there are no user groups
			if( !$groupsFromOption || !is_array( $groupsFromOption ) || empty( $groupsFromOption ) ){
				return false;
			}

			// Looks like we have some
			return true;

		}/* userGroupsExist() */


		/**
		 * Load our JS for the admin
		 *
		 * @since 0.1
		 *
		 * @param null
		 * @return null
		 */

		public function admin_enqueue_scripts__loadJS( $hook )
		{

			if( $hook != 'users_page_studiorum-user-groups' ){
				return;
			}

			wp_enqueue_script( 'studiorum-user-groups-automatic', trailingslashit( STUDIORUM_USER_GROUPS_AUTOMATIC_URL ) . 'includes/admin/assets/js/studiorum-user-groups-automatic.js', array( 'jquery', 'selectize-loader' ) );

			// Send some data to our JS
			$automaticLocalizationData = array();

			$automaticLocalizationData['numOfUsers'] = $this->numberOfUsers();
			$automaticLocalizationData['initialGroups'] = $this->calculateInitialGroups();

			wp_localize_script( 'studiorum-user-groups-automatic', 'augData', $automaticLocalizationData );

		}/* admin_enqueue_scripts__loadJS() */


		/**
		 * Load our admin CSS
		 *
		 * @since 0.1
		 *
		 * @param 
		 * @return 
		 */

		public function admin_enqueue_scripts__loadCSS()
		{

			wp_enqueue_style( 'studiorum-user-groups-automatic', trailingslashit( STUDIORUM_USER_GROUPS_AUTOMATIC_URL ) . 'includes/admin/assets/css/studiorum-user-groups-automatic.css' );

		}/* admin_enqueue_scripts__loadCSS() */


		/**
		 * Calculate the number of users for this site. 
		 *
		 * @since 0.1
		 *
		 * @param null
		 * @return int $numberOfUsers The number of users on this site
		 */

		public function numberOfUsers()
		{

			if( isset( $this->numberOfUsers ) && $this->numberOfUsers ){
				return $this->numberOfUsers;
			}

			global $localizationData;

			// In order to help out we'll let the user know how many users there are
			$numberOfUsers = count( $localizationData['userData'] );

			// Cache it so we don't keep calling count()
			$this->numberOfUsers = $numberOfUsers;

			// Ship
			return $numberOfUsers;

		}/* numberOfUsers() */


		/**
		 * Calculate the initial example group sizes. This basically square roots the number of users (rounding to the nearest integer)
		 * and uses that as the number of groups. Then calculate the number of people to put in those groups and also the number of 
		 * outliers if any.
		 *
		 * @since 0.1
		 *
		 * @param null
		 * @return array $initialGroups An associative array containing initial example number of groups and people in those groups
		 */

		public function calculateInitialGroups()
		{

			$totalNumberOfUsers = $this->numberOfUsers;

			// Let's just check we have some, otherwise it's zero-math time and that always ends badly.
			if( !$totalNumberOfUsers || $totalNumberOfUsers == 0 ){
				return false;
			}

			// e.g. If we have 20 users this will be 4
			$numOfGroups = round( sqrt( $totalNumberOfUsers ) );

			// Now calculate the other number and the outlier
			$otherNumberAndOutlier 	= $this->calcIntFactorWithOutliers( $numOfGroups, $totalNumberOfUsers );

			// Form our array
			$initialGroups = array(
				'numGroups' 		=> $numOfGroups,
				'numUsersPerGroup' 	=> $otherNumberAndOutlier['factor'],
				'numOutliers' 		=> $otherNumberAndOutlier['outliers']
			);

			return apply_filters( 'studiorum_user_groups_automatic_initial_groups_numbers', $initialGroups );

		}/* calculateInitialGroups() */


		/**
		 * Helper method which helps calculate one integer which will multiple with the one provided 
		 * to be as close to the total as possible - without going over - and also return the number 
		 * of outliers.
		 *
		 * i.e. If $total is 20, and $initial is 7, the $otherNumber will be 2 and $outliers is 6
		 * (7*2)+6 = 20
		 *
		 * @since 0.1
		 *
		 * @param int $initial One of the multiplication numbers
		 * @param int $total the total to which we need the two numbers to come to (with outliers)
		 * @return array $answer - an associative array with the 'otherNumber' and 'outliers'
		 */

		private function calcIntFactorWithOutliers( $initial = false, $result = false )
		{

			// Round down the result/initial to get an intiger of the other number
			$factor = floor( $result / $initial );

			// Calculate the outliers
			$outliers = $result - ( $initial * $factor );

			// Form our answer
			$answer = array( 'factor' => $factor, 'outliers' => $outliers );

			// Ship
			return $answer;

		}/* calcIntFactorsWithOutliers() */

	}/* class Studiorum_User_Groups_Automatic() */

	$Studiorum_User_Groups_Automatic = new Studiorum_User_Groups_Automatic();