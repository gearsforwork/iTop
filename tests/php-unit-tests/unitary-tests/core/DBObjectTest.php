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

/**
 * Created by PhpStorm.
 * User: Eric
 * Date: 02/10/2017
 * Time: 13:58
 */

namespace Combodo\iTop\Test\UnitTest\Core;

use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use CoreCannotSaveObjectException;
use DBObject;
use Exception;
use MetaModel;
use Organization;
use Person;
use PHPUnit\Framework\AssertionFailedError;
use UserRights;
use const UR_ACTION_CREATE;
use const UR_ALLOWED_YES;


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


	public function testInsertObjectWithOutOfSiloExtKeyWithDemoOrgUser(): void
	{
		/** @var Organization $oDemoOrg */
		$oDemoOrg = MetaModel::GetObjectByName(Organization::class, 'Demo');
		/** @var Organization $oDemoOrg */
		$oItDepartementOrg = MetaModel::GetObjectByName(Organization::class, 'IT Department');
		/** @var Organization $oMyCompanyOrg */
		$oMyCompanyOrg = MetaModel::GetObjectByName(Organization::class, 'My Company/Department');

		$sConfigurationManagerProfileId = 3;
		$oUserWithAllowedOrgs = $this->CreateContactlessUser('demo', $sConfigurationManagerProfileId);
		/** @var \URP_UserOrg $oUserOrg */
		$oUserOrg = \MetaModel::NewObject('URP_UserOrg', ['allowed_org_id' => $oDemoOrg->GetKey(),]);
		$oAllowedOrgList = $oUserWithAllowedOrgs->Get('allowed_org_list');
		$oAllowedOrgList->AddItem($oUserOrg);
		$oUserWithAllowedOrgs->Set('allowed_org_list', $oAllowedOrgList);
		$oUserWithAllowedOrgs->DBWrite();

		UserRights::Login($oUserWithAllowedOrgs->Get('login'));

		$this->assertSame(
			UR_ALLOWED_YES,
			UserRights::IsActionAllowed(Person::class, UR_ACTION_CREATE),
			'Test requirement : the test user must be able to create a Person object'
		);

		$oPerson1 = $this->CreatePerson(1, $oDemoOrg->GetKey());
		$this->assertTrue(true, 'we should be able to create a new Person with our same org !');
		$this->assertIsObject($oPerson1, 'we should be able to create a new Person with our same org !');

		$oPerson1->Set('org_id', $oMyCompanyOrg->GetKey());
		try {
			$oPerson1->DBWrite();
		} catch (AssertionFailedError $e) {
			/** @noinspection PhpExceptionImmediatelyRethrownInspection */
			throw $e; // handles the fail() call just above
		} /** @noinspection PhpRedundantCatchClauseInspection */
		catch (CoreCannotSaveObjectException $eCannotSave) {
			$this->assertSame(Person::class, $eCannotSave->getObjectClass());
			$this->assertCount(1, $eCannotSave->getIssues());
			$this->assertContains(Organization::class . '::' . $oMyCompanyOrg->GetKey(), $eCannotSave->getIssues()[0]);
		} catch (Exception $e) {
			$this->fail('When creating a Person object on a non allowed org, an error was thrown but not the expected one: ' . $e->getMessage());
		}

		try {
			/** @noinspection PhpUnusedLocalVariableInspection */
			$oPerson2 = $this->CreatePerson(1, $oMyCompanyOrg->GetKey());
			$this->fail('We tried to create a Person object on a non allowed org and it worked, but it should throw an error !');
		} catch (AssertionFailedError $e) {
			/** @noinspection PhpExceptionImmediatelyRethrownInspection */
			throw $e; // handles the fail() call just above
		} /** @noinspection PhpRedundantCatchClauseInspection */
		catch (CoreCannotSaveObjectException $eCannotSave) {
			$this->assertSame(Person::class, $eCannotSave->getObjectClass());
			$this->assertCount(1, $eCannotSave->getIssues());
			$this->assertContains(Organization::class . '::' . $oMyCompanyOrg->GetKey(), $eCannotSave->getIssues()[0]);
		} catch (Exception $e) {
			$this->fail('When creating a Person object on a non allowed org, an error was thrown but not the expected one: ' . $e->getMessage());
		}
	}

	public function testInsertObjectWithOutOfSiloExtKeyWithAdminUser(): void
	{
		/** @var Organization $oDemoOrg */
		$oDemoOrg = MetaModel::GetObjectByName(Organization::class, 'Demo');
		/** @var Organization $oMyCompanyOrg */
		$oMyCompanyOrg = MetaModel::GetObjectByName(Organization::class, 'My Company/Department');

		UserRights::Login('admin');

		$oPerson1 = $this->CreatePerson(1, $oDemoOrg->GetKey());
		$this->assertTrue(true, 'we should be able to create a new Person in any org');
		$this->assertIsObject($oPerson1, 'we should be able to create a new Person in any org');

		$oPerson1->Set('org_id', $oMyCompanyOrg->GetKey());
		$oPerson1->DBWrite();
		$this->assertTrue(true, 'we should be able to update a Person in any org');

		/** @noinspection PhpUnusedLocalVariableInspection */
		$oPerson2 = $this->CreatePerson(1, $oMyCompanyOrg->GetKey());
		$this->assertTrue(true, 'we should be able to create a new Person in any org');
		$this->assertIsObject($oPerson1, 'we should be able to create a new Person in any org');
	}

	public function testInsertObjectWithOutOfSiloExtKeyWithOneOrgUser(): void
	{
		/** @var Organization $oDemoOrg */
		$oDemoOrg = MetaModel::GetObjectByName(Organization::class, 'Demo');
		/** @var Organization $oMyCompanyOrg */
		$oMyCompanyOrg = MetaModel::GetObjectByName(Organization::class, 'My Company/Department');

		$sConfigurationManagerProfileId = 3;
		$oOneOrgUser = $this->CreateContactlessUser('demo', $sConfigurationManagerProfileId);
		UserRights::Login($oOneOrgUser->Get('login'));

		$oPerson1 = $this->CreatePerson(1, $oDemoOrg->GetKey());
		$this->assertTrue(true, 'we should be able to create a new Person in any org');
		$this->assertIsObject($oPerson1, 'we should be able to create a new Person in any org');

		$oPerson1->Set('org_id', $oMyCompanyOrg->GetKey());
		$oPerson1->DBWrite();
		$this->assertTrue(true, 'we should be able to update a Person in any org');

		/** @noinspection PhpUnusedLocalVariableInspection */
		$oPerson2 = $this->CreatePerson(1, $oMyCompanyOrg->GetKey());
		$this->assertTrue(true, 'we should be able to create a new Person in any org');
		$this->assertIsObject($oPerson1, 'we should be able to create a new Person in any org');
	}
}
