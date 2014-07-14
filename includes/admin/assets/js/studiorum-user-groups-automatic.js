jQuery( document ).ready( function( $ ){
console.log( sugData );
	$( '.groups-and-users-container' ).on( 'keyup', 'input', alterGroupInputsAutomatically );

	var totalUsers 			= augData.numOfUsers;

	var initialNumGroups 	= augData.initialGroups.numGroups;
	var initialNumPerGroup 	= augData.initialGroups.numUsersPerGroup;
	var initialNumOutliers 	= augData.initialGroups.numOutliers


	/**
	 * Set up the initial state of the inputs. This means auto-filling the 
	 * number of groups and users per group as well as showing the outliers
	 * fields if there are outliers
	 *
	 * @since 0.1
	 *
	 * @param null
	 * @return null
	 */

	function setupInitialSettings(){

		// When we first load, we set the initial groups up
		$( '#random-num-groups' ).val( initialNumGroups );
		$( '#random-num-users-per-group' ).val( initialNumPerGroup );

		// Also, if there are outliers, show the outliers fields and update the message
		if( initialNumOutliers && initialNumOutliers > 0 ){
			showOutliersFieldsAndText( initialNumOutliers );
		}else{
			hideOutliersFields();
		}

	}/* setupInitialSettings() */


	/**
	 * When either the number of groups or number of users per group changes
	 * we automatically adjust the other input as well as showing the outliers
	 * fields should the changes require it 
	 *
	 * @since 0.1
	 *
	 * @param null
	 * @return null
	 */
	function alterGroupInputsAutomatically( event )
	{

		// Which field has been changed?
		var fieldChanged = event.currentTarget;

		// And to what value has it has been changed
		var valueChangedTo = fieldChanged.value;

		// If that value hasn't been changed to an empty string or a zero, we can go ahead and calculate the other factor and the outliers
		if( valueChangedTo == '' || valueChangedTo == '0' ){
			return;
		}
		
		// If they enter a number more than there are users, well...no
		if( parseInt( valueChangedTo ) > parseInt( totalUsers ) ){
			displayErrorMessage( 'You have chosen more users or groups than there are users on this site.' );
		}else{
			hideErrorMessage();
		}

		var factorAndOutliers 	= calcIntFactorWithOutliers( valueChangedTo, totalUsers );

		var factor 				= factorAndOutliers.factor;
		var outliers 			= factorAndOutliers.outliers;

		// Grab the *other* input by grabbing all the inputs and then saying, not fieldChanged
		var bothFields = $( '.groups-and-users-container input' );

		var otherField = bothFields.not( $( fieldChanged ) );

		// Set the other field value
		otherField.val( factor );

		// If we have outliers, show the fields and update the text
		if( outliers && outliers > 0 ){
			showOutliersFieldsAndText( outliers );
		}else{
			hideOutliersFields();
		}

	}/* alterGroupInputsAutomatically() */


	/**
	 * A JS equivalent of our PHP method which calculates the factor based on the total result and
	 * the 'other' number
	 *
	 * @since 0.1
	 *
	 * @param int $initial One of the multiplication numbers
	 * @param int $total the total to which we need the two numbers to come to (with outliers)
	 * @return object $answer - the 'otherNumber' and 'outliers'
	 */

	function calcIntFactorWithOutliers( initial, result )
	{

		// Round down the result/initial to get an intiger of the other number
		var factor 		= Math.floor( result / initial );

		// Calculate the outliers
		var outliers 	= result - ( initial * factor );

		// Form our answer
		var answer 		= {};
		answer.factor 	= factor;
		answer.outliers = outliers;

		return answer;

	}/* function calcIntFactorWithOutliers() */


	/**
	 * When we have outliers, show the fields and change the text to indiciate how many
	 *
	 * @since 0.1
	 *
	 * @param string $param description
	 * @return string|int returnDescription
	 */

	function showOutliersFieldsAndText( numOfOutliers )
	{

		$( '.numOutliers' ).html( numOfOutliers );
		$( '.handle-outliers-random-fields' ).show();

	}/* showOutliersFieldsAndText() */


	/**
	 * Hide the outliers fields when there are no outliers
	 *
	 * @since 0.1
	 *
	 * @param null
	 * @return null
	 */

	function hideOutliersFields()
	{

		$( '.handle-outliers-random-fields' ).fadeOut( 70 );

	}/* hideOutliersFields() */


	/**
	 * Helper method to show an error message. Displays the message given and
	 * fades in the message container
	 *
	 * @since 0.1
	 *
	 * @param string message The message to show
	 * @return null
	 */

	function displayErrorMessage( message )
	{

		$( '.error-message-container' ).html( '<p>' + message + '</p>' ).fadeIn();

	}/* displayErrorMessage() */

	/**
	 * Hide any error message when there's no error
	 *
	 * @since 0.1
	 *
	 * @param null
	 * @return null
	 */

	function hideErrorMessage()
	{

		$( '.error-message-container' ).fadeOut();

	}/* hideErrorMessage() */


	// Set things up to start
	setupInitialSettings();

} );