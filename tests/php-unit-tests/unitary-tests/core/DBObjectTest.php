<?php
// Copyright (c) 2010-2017 Combodo SARL
//
//   This file is part of iTop.
//
//   iTop is free software; you can redistribute it and/or modify
//   it under the terms of the GNU Affero General Public License as published by
//   the Free Software Foundation, either version 3 of the License, or
//   (at your option) any later version.
//
//   iTop is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU Affero General Public License for more details.
//
//   You should have received a copy of the GNU Affero General Public License
//   along with iTop. If not, see <http://www.gnu.org/licenses/>
//

namespace Combodo\iTop\Test\UnitTest\Core;

use Attachment;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use DBObject;
use InvalidExternalKeyValueException;
use MetaModel;
use Organization;
use Person;
use Server;
use User;
use UserRights;


/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 */
class DBObjectTest extends ItopDataTestCase
{
	const CREATE_TEST_ORG = true;

	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('core/coreexception.class.inc.php');
		$this->RequireOnceItopFile('core/dbobject.class.php');
	}

	/**
	 * Test default page name
	 */
	public function testGetUIPage()
	{
		static::assertEquals('UI.php', DBObject::GetUIPage());
	}

	/**
	 * Test PKey validation
	 * @dataProvider keyProviderOK
	 * @param $key
	 * @param $res
	 */
	public function testIsValidPKeyOK($key, $res)
	{
		static::assertEquals(DBObject::IsValidPKey($key), $res);
	}

	public function keyProviderOK()
	{
		return array(
			array(1, true),
			array('255', true),
			array(-24576, true),
			array(0123, true),
			array(0xCAFE, true),
			array(PHP_INT_MIN, true),
			array(PHP_INT_MAX, true),
			array('test', false),
			array('', false),
			array('a255', false),
			array('PHP_INT_MIN', false));
	}

	public function testGetOriginal()
	{
		$oObject = $this->CreateUserRequest(190664);

		static::assertNull($oObject->GetOriginal('sla_tto_passed'));
	}

	/**
	 * @return void
	 * @throws \Exception
	 */
	public function testListPreviousValuesForUpdatedAttributes()
	{
		$oOrg = $this->CreateOrganization('testListPreviousValuesForUpdatedAttributes');

		$this->assertCount(0, $oOrg->ListChanges());
		$oOrg->Set('code', strtoupper('testListPreviousValuesForUpdatedAttributes'));
		$this->assertCount(1, $oOrg->ListChanges());
		$oOrg->DBUpdate();
		$this->assertCount(0, $oOrg->ListChanges());
		$this->assertCount(1, $oOrg->ListPreviousValuesForUpdatedAttributes());

		$oOrg->DBUpdate();

		$this->assertCount(0, $oOrg->ListChanges());
		$this->assertCount(1, $oOrg->ListPreviousValuesForUpdatedAttributes());

		$oOrg->DBDelete();

		$oOrg = MetaModel::NewObject('Organization');
		$oOrg->Set('name', 'testListPreviousValuesForUpdatedAttributes');
		$oOrg->DBInsert();
		$oOrg->Set('code', strtoupper('testListPreviousValuesForUpdatedAttributes'));
		$oOrg->DBUpdate();
		$oOrg->DBUpdate();
		$this->markTestIncomplete('This test has not been implemented yet. wait for N°4967 fix');
		$this->debug("ERROR: N°4967 - 'Previous Values For Updated Attributes' not updated if DBUpdate is called without modifying the object");
		//$this->assertCount(0, $oOrg->ListPreviousValuesForUpdatedAttributes());
	}

	public function testCheckExtKeysSiloOnAttributeExternalKeyWithCallbacks()
	{
		// Preparing data...
		$oAlwaysTrueCallback = function () {
			return true;
		};
		$oAlwaysFalseCallback = function () {
			return false;
		};

		/** @var Organization $oDemoOrg */
		$oDemoOrg = MetaModel::GetObjectByName(Organization::class, 'Demo');
		/** @var Organization $oMyCompanyOrg */
		$oMyCompanyOrg = MetaModel::GetObjectByName(Organization::class, 'My Company/Department');

		/** @var Person $oPersonOfDemoOrg */
		$oPersonOfDemoOrg = MetaModel::GetObjectByName(Person::class, 'Agatha Christie');
		/** @var Person $oPersonOfMyCompanyOrg */
		$oPersonOfMyCompanyOrg = MetaModel::GetObjectByName(Person::class, 'My first name My last name');

		$sConfigurationManagerProfileId = 3; // Access to Person objects
		$oUserWithAllowedOrgs = $this->CreateDemoOrgUser($oDemoOrg, $sConfigurationManagerProfileId);

		$oAdminUser = MetaModel::GetObjectByName(User::class, 'admin');
		if (is_null($oAdminUser)) {
			$oAdminUser = $this->CreateUser('admin', 1);
		}

		// We could use the CreatePerson, but no need to persist the new object !
		/** @var Person $oPersonObject */
		$oPersonObject = MetaModel::NewObject(Person::class, [
			'name' => 'Person_Test_' . __CLASS__ . '_' . __METHOD__,
			'first_name' => 'Test',
			'org_id' => $oMyCompanyOrg->GetKey(),
		]);
		// Persisting so that extkeys won't be part of changes : their invalid values must NOT be tested
		$oPersonObject->DBWrite();

		// Now we can do some tests !
		UserRights::Login($oUserWithAllowedOrgs->Get('login'));
		$oPersonObject->CheckChangedExtKeysValues();
		$this->assertTrue(true, 'no change, we must be OK even if invalid values exists in the object');

		$oPersonObject->Set('manager_id', $oPersonOfDemoOrg->GetKey());
		$oPersonObject->CheckChangedExtKeysValues();
		$this->assertTrue(true, 'valid extkey value : manager is in the allowed org');

		$oPersonObject->CheckChangedExtKeysValues($oAlwaysTrueCallback);
		$this->assertTrue(true, 'Always true callback should avoid error');

		try {
			$oPersonObject->CheckChangedExtKeysValues($oAlwaysFalseCallback);
			$this->fail('Always false callback should always throw an error');
		} catch (InvalidExternalKeyValueException $eCannotSave) {
			$this->assertTrue(true, 'Always false callback should always throw an error');
		}

		$oPersonObject->Set('manager_id', $oPersonOfMyCompanyOrg->GetKey());
		try {
			$oPersonObject->CheckChangedExtKeysValues();
			$this->fail('Creating a Person with an non allowed org should throw an exception !');
		} catch (InvalidExternalKeyValueException $eCannotSave) {
			$this->assertEquals('manager_id', $eCannotSave->GetAttCode());
			$this->assertEquals($oMyCompanyOrg->GetKey(), $eCannotSave->GetAttValue());
		}

		$oPersonObject->CheckChangedExtKeysValues($oAlwaysTrueCallback);
		$this->assertTrue(true, 'Always true callback should avoid error');

		try {
			$oPersonObject->CheckChangedExtKeysValues($oAlwaysFalseCallback);
			$this->fail('Always false callback should always throw an error');
		} catch (InvalidExternalKeyValueException $eCannotSave) {
			$this->assertTrue(true, 'Always false callback should always throw an error');
		}

		// ugly hack to remove caches SQL query :(
		// In 3.0+ this won't be necessary anymore thanks to UserRights::Logoff
		$this->SetNonPublicStaticProperty(MetaModel::class, 'aQueryCacheGetObject', []);

		UserRights::Login($oAdminUser->Get('login'));
		$oPersonObject->CheckChangedExtKeysValues();
		$this->assertTrue(true, 'Admin user can create objects in any org');
	}

	public function testCheckExtKeysSiloOnAttributeLinkedSetIndirect()
	{
		// Preparing data...
		/** @var Organization $oDemoOrg */
		$oDemoOrg = MetaModel::GetObjectByName(Organization::class, 'Demo');
		/** @var Organization $oItDepartmentOrg */
		$oItDepartmentOrg = MetaModel::GetObjectByName(Organization::class, 'IT Department');
		/** @var Server $oServerOnItDepartmentOrg */
		$oServerOnItDepartmentOrg = $this->createObject(Server::class, [
			'name' => 'test server',
			'org_id' => $oItDepartmentOrg->GetKey(),
		]);

		$sSupportAgentProfileId = 5; // access to UserRequest objects
		$oUserWithAllowedOrgs = $this->CreateDemoOrgUser($oDemoOrg, $sSupportAgentProfileId);


		// Now we can do some tests !
		UserRights::Login($oUserWithAllowedOrgs->Get('login'));
		$oTicketOnDemoOrg = $this->CreateUserRequest(0, ['org_id' => $oDemoOrg->GetKey()]);

		/** @var Server $oServer1 */
		$oServer1 = MetaModel::GetObjectByName(Server::class, 'Server1');
		$this->AddCIToTicket($oServer1, $oTicketOnDemoOrg);
		$oTicketOnDemoOrg->CheckChangedExtKeysValues();
		$this->assertTrue(true, 'Should be able to add an allowed org CI to a ticket');
		$oTicketOnDemoOrg->DBWrite();

		$this->AddCIToTicket($oServerOnItDepartmentOrg, $oTicketOnDemoOrg);
		try {
			$oTicketOnDemoOrg->CheckChangedExtKeysValues();
			$this->fail('There should be an error on ticket pointing to a non allowed org server');
		} catch (InvalidExternalKeyValueException $e) {
			// we are getting the exception on the lnk class
			// In consequence attcode is `lnkFunctionalCIToTicket.functionalci_id` instead of `Ticket.functionalcis_list`
			$this->assertEquals('functionalci_id', $e->GetAttCode());
			$this->assertEquals($oServerOnItDepartmentOrg->GetKey(), $e->GetAttValue());
		}
	}

	public function testCheckExtKeysSiloOnAttributeObjectKey()
	{
		// Preparing data...
		/** @var Organization $oDemoOrg */
		$oDemoOrg = MetaModel::GetObjectByName(Organization::class, 'Demo');
		/** @var Organization $oMyCompanyOrg */
		$oMyCompanyOrg = MetaModel::GetObjectByName(Organization::class, 'My Company/Department');

		$oTicketOnDemoOrg = $this->CreateUserRequest(0, ['org_id' => $oDemoOrg->GetKey()]);
		$oTicketOnMyCompanyOrg = $this->CreateUserRequest(1, ['org_id' => $oMyCompanyOrg->GetKey()]);

		$sSupportAgentProfileId = 5;
		$oUserWithAllowedOrgs = $this->CreateDemoOrgUser($oDemoOrg, $sSupportAgentProfileId);


		// Now we can do some tests !
		UserRights::Login($oUserWithAllowedOrgs->Get('login'));

		$oAttachmentOnDemoOrgTicket = MetaModel::NewObject(Attachment::class, [
			'item_class' => get_class($oTicketOnDemoOrg),
			'item_id' => $oTicketOnDemoOrg->GetKey(),
		]);
		$oAttachmentOnDemoOrgTicket->CheckChangedExtKeysValues();
		$this->assertTrue(true, 'Should be able to create an attachment pointing to a ticket in the allowed org list');

		$oAttachmentOnMyCompanyOrgTicket = MetaModel::NewObject(Attachment::class, [
			'item_class' => get_class($oTicketOnMyCompanyOrg),
			'item_id' => $oTicketOnMyCompanyOrg->GetKey(),
		]);
		try {
			$oAttachmentOnMyCompanyOrgTicket->CheckChangedExtKeysValues();
			$this->fail('There should be an error on attachment pointing to a non allowed org ticket');
		} catch (InvalidExternalKeyValueException $e) {
			$this->assertEquals('item_id', $e->GetAttCode());
			$this->assertEquals($oTicketOnMyCompanyOrg->GetKey(), $e->GetAttValue());
		}
	}

	private function CreateDemoOrgUser(Organization $oDemoOrg, string $sProfileId): User
	{
		$oUserWithAllowedOrgs = $this->CreateContactlessUser('demo_test_' . __CLASS__, $sProfileId);
		/** @var \URP_UserOrg $oUserOrg */
		$oUserOrg = \MetaModel::NewObject('URP_UserOrg', ['allowed_org_id' => $oDemoOrg->GetKey(),]);
		$oAllowedOrgList = $oUserWithAllowedOrgs->Get('allowed_org_list');
		$oAllowedOrgList->AddItem($oUserOrg);
		$oUserWithAllowedOrgs->Set('allowed_org_list', $oAllowedOrgList);
		$oUserWithAllowedOrgs->DBWrite();

		return $oUserWithAllowedOrgs;
	}
}
