<?php
/**
 * Implements Special:ChangeEmail
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup SpecialPage
 */
namespace UserSnoop\Special;

use FormSpecialPage;
use Hooks;
use HTMLForm;
use Sanitizer;
use Status;
use Title;
use User;

/**
 * Let users change their email address.
 *
 * @ingroup SpecialPage
 */
class ChangeUserEmail extends FormSpecialPage {
	/**
	 * @var Status
	 */
	private $status;
	private $user;
	public function __construct() {
		parent::__construct( 'ChangeUserEmail', 'usersnoop' );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * Main execution point
	 * @param string $par
	 */
	function execute( $par ) {
		$out = $this->getOutput();
		$out->disallowUserJs();

		$this->user = User::newFromName( $par );
		if ( !$par ) {
			$this->output->redirect(
				Title::newFromText( "UserSnoop", NS_SPECIAL )->getFullURL()
			);
		}

		parent::execute( $par );
	}

	protected function getFormFields() {
		$fields = [
			'Name' => [
				'type' => 'info',
				'label-message' => 'username',
				'default' => $this->user->getName(),
			],
			'OldEmail' => [
				'type' => 'info',
				'label-message' => 'changeemail-oldemail',
				'default' => $this->user->getEmail() ?: $this->msg( 'changeemail-none' )->text(),
			],
			'NewEmail' => [
				'type' => 'email',
				'label-message' => 'changeemail-newemail',
				'autofocus' => true,
				'help-message' => 'usersnoop-changeemail-newemail-help',
			],
		];

		return $fields;
	}

	protected function getDisplayFormat() {
		return 'ooui';
	}

	protected function alterForm( HTMLForm $form ) {
		$form->setId( 'mw-changeemail-form' );
		$form->setTableId( 'mw-changeemail-table' );
		$form->setSubmitTextMsg( 'changeemail-submit' );
		$form->addHiddenFields(
			$this->getRequest()->getValues( 'returnto', 'returntoquery' )
		);

		$form->addHeaderText(
			$this->msg( 'usersnoop-changeuseremail-header', $this->user )->parseAsBlock()
		);
	}

	public function onSubmit( array $data ) {
		$password = isset( $data['Password'] ) ? $data['Password'] : null;
		$status = $this->attemptChange( $this->user, $password, $data['NewEmail'] );

		$this->status = $status;

		return $status;
	}

	public function onSuccess() {
		$request = $this->getRequest();

		$returnto = $request->getVal( 'returnto' );
		$titleObj = $returnto !== null ? Title::newFromText( $returnto ) : null;
		if ( !$titleObj instanceof Title ) {
			$titleObj = Title::newMainPage();
		}
		$query = $request->getVal( 'returntoquery' );

		if ( $this->status->value === true ) {
			$this->getOutput()->redirect( $titleObj->getFullURL( $query ) );
		} elseif ( $this->status->value === 'eauth' ) {
			# Notify user that a confirmation email has been sent...
			$this->getOutput()->wrapWikiMsg(
				"<div class='error' style='clear: both;'>\n$1\n</div>",
				'usersnoop-eauthentsent', $this->getUser()->getName()
			);
			// just show the link to go back
			$this->getOutput()->addReturnTo( $titleObj, wfCgiToArray( $query ) );
		}
	}

	/**
	 * @param User $user
	 * @param string $pass
	 * @param string $newaddr
	 * @return Status
	 */
	private function attemptChange( User $user, $pass, $newaddr ) {
		if ( $newaddr != '' && !Sanitizer::validateEmail( $newaddr ) ) {
			return Status::newFatal( 'invalidemailaddress' );
		}

		if ( $newaddr === $user->getEmail() ) {
			return Status::newFatal( 'changeemail-nochange' );
		}

		$oldaddr = $user->getEmail();
		$status = $user->setEmailWithConfirmation( $newaddr );
		if ( !$status->isGood() ) {
			return $status;
		}

		Hooks::run( 'PrefsEmailAudit', [ $user, $oldaddr, $newaddr ] );

		$user->saveSettings();
		return $status;
	}

	public function requiresUnblock() {
		return false;
	}

	protected function getGroupName() {
		return 'users';
	}
}
