<?php
/**
 * Copyright (C) 2013-2020 Combodo SARL
 *
 * This file is part of iTop.
 *
 * iTop is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * iTop is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 */

namespace Combodo\iTop\Application\UI\Base\Layout\ActivityPanel;


use appUserPreferences;
use AttributeDateTime;
use cmdbAbstractObject;
use Combodo\iTop\Application\UI\Base\Layout\ActivityPanel\ActivityEntry\ActivityEntry;
use Combodo\iTop\Application\UI\Base\Layout\ActivityPanel\ActivityEntry\CaseLogEntry;
use Combodo\iTop\Application\UI\Base\Layout\ActivityPanel\CaseLogEntryForm\CaseLogEntryForm;
use Combodo\iTop\Application\UI\Base\UIBlock;
use DBObject;
use Exception;
use MetaModel;

/**
 * Class ActivityPanel
 *
 * @author Guillaume Lajarige <guillaume.lajarige@combodo.com>
 * @package Combodo\iTop\Application\UI\Base\Layout\ActivityPanel
 * @internal
 * @since 3.0.0
 */
class ActivityPanel extends UIBlock
{
	// Overloaded constants
	public const BLOCK_CODE = 'ibo-activity-panel';
	public const DEFAULT_HTML_TEMPLATE_REL_PATH = 'base/layouts/activity-panel/layout';
	public const DEFAULT_JS_TEMPLATE_REL_PATH = 'base/layouts/activity-panel/layout';
	public const DEFAULT_JS_FILES_REL_PATH = [
		'js/layouts/activity-panel/activity-panel.js',
	];

	/**
	 * @var bool
	 * @see static::$bShowMultipleEntriesSubmitConfirmation
	 */
	public const DEFAULT_SHOW_MULTIPLE_ENTRIES_SUBMI_CONFIRMATION = true;

	/** @var \DBObject $oObject The object for which the activity panel is for */
	protected $oObject;
	/**
	 * @var string $sObjectMode Display mode of $oObject (create, edit, view, ...)
	 * @see \cmdbAbstractObject::ENUM_OBJECT_MODE_XXX
	 */
	protected $sObjectMode;
	/** @var array $aCaseLogs Metadata of the case logs (att. code, color, ...), will be use to make the tabs and identify them easily */
	protected $aCaseLogs;
	/** @var ActivityEntry[] $aEntries */
	protected $aEntries;
	/** @var bool $bAreEntriesSorted True if the entries have been sorted by date */
	protected $bAreEntriesSorted;
	/**
	 * @var bool True if the host object has states (but not necessary a lifecycle)
	 * @see MetaModel::HasStateAttributeCode()
	 */
	protected $bHasStates;
	/** @var \Combodo\iTop\Application\UI\Base\Layout\ActivityPanel\CaseLogEntryForm\CaseLogEntryForm[] $aCaseLogTabsEntryForms */
	protected $aCaseLogTabsEntryForms;
	/** @var bool Whether a confirmation dialog should be prompt when multiple entries are about to be submitted at once */
	protected $bShowMultipleEntriesSubmitConfirmation;

	/**
	 * ActivityPanel constructor.
	 *
	 * @param \DBObject $oObject
	 * @param ActivityEntry[] $aEntries
	 * @param string|null $sId
	 *
	 * @throws \CoreException
	 * @throws \Exception
	 */
	public function __construct(DBObject $oObject, array $aEntries = [], ?string $sId = null)
	{
		parent::__construct($sId);

		$this->InitializeCaseLogTabs();
		$this->InitializeCaseLogTabsEntryForms();
		$this->SetObject($oObject);
		$this->SetObjectMode(cmdbAbstractObject::DEFAULT_OBJECT_MODE);
		$this->SetEntries($aEntries);
		$this->bAreEntriesSorted = false;
		$this->ComputedShowMultipleEntriesSubmitConfirmation();
	}

	/**
	 * Set the object the panel is for, and initialize the corresponding case log tabs.
	 *
	 * @param \DBObject $oObject
	 *
	 * @return $this
	 * @throws \CoreException
	 * @throws \Exception
	 */
	protected function SetObject(DBObject $oObject)
	{
		$this->oObject = $oObject;
		$sObjectClass = get_class($this->oObject);

		// Check if object has a lifecycle
		$this->bHasStates = MetaModel::HasStateAttributeCode($sObjectClass);

		// Initialize the case log tabs
		$this->InitializeCaseLogTabs();
		$this->InitializeCaseLogTabsEntryForms();

		// Get only case logs from the "details" zlist, but if none (2.7 and older) show them all
		$aCaseLogAttCodes = MetaModel::GetCaseLogs($sObjectClass, 'details');
		if (empty($aCaseLogAttCodes)) {
			$aCaseLogAttCodes = MetaModel::GetCaseLogs($sObjectClass);
		}
		
		foreach ($aCaseLogAttCodes as $sCaseLogAttCode) {
			$this->AddCaseLogTab($sCaseLogAttCode);
		}

		return $this;
	}

	/**
	 * Return the object for which the activity panel is for
	 *
	 * @return \DBObject
	 */
	public function GetObject()
	{
		return $this->oObject;
	}

	/**
	 * Return the object id for which the activity panel is for
	 *
	 * @return int
	 */
	public function GetObjectId(): int {
		return $this->oObject->GetKey();
	}

	/**
	 * Return the object class for which the activity panel is for
	 *
	 * @return string
	 */
	public function GetObjectClass(): string {
		return get_class($this->oObject);
	}

	/**
	 * Set the display mode of the $oObject
	 *
	 * @param string $sMode
	 * @see cmdbAbstractObject::ENUM_OBJECT_MODE_XXX
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function SetObjectMode(string $sMode)
	{
		// Consistency check
		if(!in_array($sMode, cmdbAbstractObject::EnumObjectModes())){
			throw new Exception("Activity panel: Object mode '$sMode' not allowed, should be either ".implode(' / ', cmdbAbstractObject::EnumObjectModes()));
		}

		$this->sObjectMode = $sMode;

		return $this;
	}

	/**
	 * Return the display mode of the $oObject
	 *
	 * @see cmdbAbstractObject::ENUM_OBJECT_MODE_XXX
	 * @return string
	 */
	public function GetObjectMode(): string
	{
		return $this->sObjectMode;
	}

	/**
	 * Set all entries at once.
	 *
	 * @param ActivityEntry[] $aEntries
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function SetEntries(array $aEntries)
	{
		// Reset entries
		$this->aEntries = [];

		foreach ($aEntries as $oEntry)
		{
			$this->AddEntry($oEntry);
		}

		return $this;
	}

	/**
	 * Return all the entries
	 *
	 * @return ActivityEntry[]
	 */
	public function GetEntries()
	{
		if ($this->bAreEntriesSorted === false)
		{
			$this->SortEntries();
		}

		return $this->aEntries;
	}

	/**
	 * Return all the entries grouped by author / origin (case log).
	 * This is useful for the template as it avoid to make the processing there.
	 *
	 * @return array
	 */
	public function GetGroupedEntries()
	{
		$aGroupedEntries = [];

		$aCurrentGroup = ['author_login' => null, 'origin' => null, 'entries' => []];
		$aPreviousEntryData = ['author_login' => null, 'origin' => null];
		foreach($this->GetEntries() as $sId => $oEntry)
		{
			// New entry data
			$sAuthorLogin = $oEntry->GetAuthorLogin();
			$sOrigin = $oEntry->GetOrigin();

			// Check if it's time to change of group
			if(($sAuthorLogin !== $aPreviousEntryData['author_login']) || ($sOrigin !== $aPreviousEntryData['origin']))
			{
				// Flush current group if necessary
				if(empty($aCurrentGroup['entries']) === false)
				{
					$aGroupedEntries[] = $aCurrentGroup;
				}

				// Init (first iteration) or reset (other iterations) current group
				$aCurrentGroup = ['author_login' => $sAuthorLogin, 'origin' => $sOrigin, 'entries' => []];
			}

			$aCurrentGroup['entries'][] = $oEntry;
			$aPreviousEntryData = ['author_login' => $sAuthorLogin, 'origin' => $sOrigin];
		}

		// Flush last group
		if(empty($aCurrentGroup['entries']) === false)
		{
			$aGroupedEntries[] = $aCurrentGroup;
		}

		return $aGroupedEntries;
	}

	/**
	 * Sort all entries based on the their date, descending.
	 *
	 * @return $this
	 */
	protected function SortEntries()
	{
		if(count($this->aEntries) > 1)
		{
			uasort($this->aEntries, function($oEntryA, $oEntryB){
				/** @var ActivityEntry $oEntryA */
				/** @var ActivityEntry $oEntryB */
				$sDateTimeA = $oEntryA->GetRawDateTime();
				$sDateTimeB = $oEntryB->GetRawDateTime();

				if ($sDateTimeA === $sDateTimeB)
				{
					return 0;
				}

				return ($sDateTimeA > $sDateTimeB) ? -1 : 1;
			});
		}
		$this->bAreEntriesSorted = true;

		return $this;
	}

	/**
	 * Add an $oEntry after all others, excepted if there is already an entry with the same ID in which case it replaces it.
	 *
	 * @param \Combodo\iTop\Application\UI\Base\Layout\ActivityPanel\ActivityEntry\ActivityEntry $oEntry
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function AddEntry(ActivityEntry $oEntry)
	{
		$this->aEntries[$oEntry->GetId()] = $oEntry;
		$this->bAreEntriesSorted = false;

		// Add case log to the panel and update metadata when necessary
		if ($oEntry instanceof CaseLogEntry)
		{
			$sCaseLogAttCode = $oEntry->GetAttCode();
			$sAuthorLogin = $oEntry->GetAuthorLogin();

			// Initialize case log metadata
			if ($this->HasCaseLogTab($sCaseLogAttCode) === false)
			{
				$this->AddCaseLogTab($sCaseLogAttCode);
			}

			// Add case log rank to the entry
			$oEntry->SetCaseLogRank($this->aCaseLogs[$sCaseLogAttCode]['rank']);

			// Update metadata
			// - Message count
			$this->aCaseLogs[$sCaseLogAttCode]['total_messages_count']++;
			// - Authors
			if(array_key_exists($sAuthorLogin, $this->aCaseLogs[$sCaseLogAttCode]['authors']) === false)
			{
				$this->aCaseLogs[$sCaseLogAttCode]['authors'][$sAuthorLogin] = [
					'messages_count' => 0,
				];
			}
			$this->aCaseLogs[$sCaseLogAttCode]['authors'][$sAuthorLogin]['messages_count']++;
		}

		return $this;
	}

	/**
	 * Remove entry of ID $sEntryId.
	 * Note that if there is no entry with that ID, it proceeds silently.
	 *
	 * @param string $sEntryId
	 *
	 * @return $this
	 */
	public function RemoveEntry(string $sEntryId)
	{
		if (array_key_exists($sEntryId, $this->aEntries))
		{
			// Recompute case logs metadata only if necessary
			$oEntry = $this->aEntries[$sEntryId];
			if ($oEntry instanceof CaseLogEntry)
			{
				$sCaseLogAttCode = $oEntry->GetAttCode();
				$sAuthorLogin = $oEntry->GetAuthorLogin();

				// Update metadata
				// - Message count
				$this->aCaseLogs[$sCaseLogAttCode]['total_messages_count']--;
				// - Authors
				$this->aCaseLogs[$sCaseLogAttCode]['authors'][$sAuthorLogin]['messages_count']--;
				if($this->aCaseLogs[$sCaseLogAttCode]['authors'][$sAuthorLogin]['messages_count'] === 0)
				{
					unset($this->aCaseLogs[$sCaseLogAttCode]['authors'][$sAuthorLogin]);
				}
			}

			unset($this->aEntries[$sEntryId]);
		}

		return $this;
	}

	/**
	 * Return true if there is at least one entry
	 *
	 * @return bool
	 */
	public function HasEntries()
	{
		return !empty($this->aEntries);
	}

	/**
	 * Return all the case log tabs metadata, not their entries
	 *
	 * @return array
	 */
	public function GetCaseLogTabs()
	{
		return $this->aCaseLogs;
	}

	/**
	 * @return $this
	 */
	protected function InitializeCaseLogTabs()
	{
		$this->aCaseLogs = [];
		return $this;
	}

	/**
	 * Add the case log tab to the panel
	 * Note: Case log entries are added separately, see static::AddEntry()
	 * Note: If hidden, the case log will not be added
	 *
	 * @param string $sAttCode
	 *
	 * @return $this
	 * @throws \Exception
	 */
	protected function AddCaseLogTab(string $sAttCode)
	{
		// Add case log only if not already existing
		if (!array_key_exists($sAttCode, $this->aCaseLogs))
		{
			$iFlags = ($this->GetObject()->IsNew()) ? $this->GetObject()->GetInitialStateAttributeFlags($sAttCode) : $this->GetObject()->GetAttributeFlags($sAttCode);
			$bIsHidden = (OPT_ATT_HIDDEN === ($iFlags & OPT_ATT_HIDDEN));
			$bIsReadOnly = (OPT_ATT_READONLY === ($iFlags & OPT_ATT_READONLY));

			// Only if not hidden
			if (false === $bIsHidden) {
				$this->aCaseLogs[$sAttCode] = [
					'rank' => count($this->aCaseLogs) + 1,
					'title' => MetaModel::GetLabel(get_class($this->oObject), $sAttCode),
					'total_messages_count' => 0,
					'authors' => [],
					'is_read_only' => $bIsReadOnly,
				];
			}
		}

		return $this;
	}

	/**
	 * Remove the case log tab from the panel.
	 * Note: Case log entries will not be removed.
	 *
	 * @param string $sAttCode
	 *
	 * @return $this
	 */
	protected function RemoveCaseLogTab(string $sAttCode)
	{
		if (array_key_exists($sAttCode, $this->aCaseLogs))
		{
			unset($this->aCaseLogs[$sAttCode]);
		}

		return $this;
	}

	/**
	 * Return true if the case log of $sIs code has been initialized.
	 *
	 * @param string $sAttCode
	 *
	 * @return bool
	 */
	public function HasCaseLogTab(string $sAttCode)
	{
		return isset($this->aCaseLogs[$sAttCode]);
	}

	/**
	 * Return true if there is at least one case log declared.
	 *
	 * @return bool
	 */
	public function HasCaseLogTabs()
	{
		return !empty($this->aCaseLogs);
	}

	/**
	 * @return bool true if there is at least 1 editable case log
	 */
	public function HasAnEditableCaseLogTab(): bool
	{
		$bHasEditable = false;

		foreach ($this->GetCaseLogTabs() as $aCaseLogTabData) {
			if (false === $aCaseLogTabData['is_read_only']) {
				$bHasEditable = true;
				break;
			}
		}

		return $bHasEditable;
	}

	/**
	 * Empty the caselogs entry forms
	 *
	 * @return $this
	 */
	protected function InitializeCaseLogTabsEntryForms()
	{
		$this->aCaseLogTabsEntryForms = [];
		return $this;
	}

	/**
	 * Return all entry forms for all case log tabs
	 *
	 * @return \Combodo\iTop\Application\UI\Base\Layout\ActivityPanel\CaseLogEntryForm\CaseLogEntryForm[]
	 */
	public function GetCaseLogTabsEntryForms(): array
	{
		return $this->aCaseLogTabsEntryForms;
	}

	/**
	 * Set the $oCaseLogEntryForm for the $sCaseLogId tab.
	 * Note: If there is no caselog for that ID, it will proceed silently.
	 *
	 * @param string                                                                              $sCaseLogId
	 * @param \Combodo\iTop\Application\UI\Base\Layout\ActivityPanel\CaseLogEntryForm\CaseLogEntryForm $oCaseLogEntryForm
	 *
	 * @return $this
	 */
	public function SetCaseLogTabEntryForm(string $sCaseLogId, CaseLogEntryForm $oCaseLogEntryForm)
	{
		if ($this->HasCaseLogTab($sCaseLogId)){
			$this->aCaseLogTabsEntryForms[$sCaseLogId] = $oCaseLogEntryForm;
		}

		return $this;
	}

	/**
	 * Return the caselog entry form for the $sCaseLogId tab
	 *
	 * @param string $sCaseLogId
	 *
	 * @return \Combodo\iTop\Application\UI\Base\Layout\ActivityPanel\CaseLogEntryForm\CaseLogEntryForm
	 */
	public function GetCaseLogTabEntryForm(string $sCaseLogId)
	{
		return $this->aCaseLogTabsEntryForms[$sCaseLogId];
	}

	/**
	 * @param string $sCaseLogId
	 *
	 * @return bool
	 */
	public function HasCaseLogTabEntryForm(string $sCaseLogId): bool
	{
		return !empty($this->aCaseLogTabsEntryForms[$sCaseLogId]);
	}

	/**
	 * @uses static::$bShowMultipleEntriesSubmitConfirmation
	 * @return bool
	 */
	public function GetShowMultipleEntriesSubmitConfirmation(): bool
	{
		return $this->bShowMultipleEntriesSubmitConfirmation;
	}

	/**
	 * Whether the submission of the case logs present in the activity panel is autonomous or will be handled by another form
	 *
	 * @return bool
	 */
	public function IsCaseLogsSubmitAutonomous(): bool
	{
		$iAutonomousSubmission = 0;
		$iBridgedSubmissions = 0;
		foreach ($this->GetCaseLogTabsEntryForms() as $oCaseLogEntryForm) {
			if ($oCaseLogEntryForm->IsSubmitAutonomous()) {
				$iAutonomousSubmission++;
			}
			else {
				$iBridgedSubmissions++;
			}
		}

		if (($iAutonomousSubmission > 0) && ($iBridgedSubmissions > 0)) {
			throw new Exception('All case logs should have the same submission mode (Autonomous: '.$iAutonomousSubmission.', Bridged: '.$iBridgedSubmissions);
		}

		return $iAutonomousSubmission > 0;
	}

	/**
	 * @uses $bHasStates
	 * @return bool
	 */
	public function HasStates(): bool
	{
		return $this->bHasStates;
	}

	/**
	 * Return the formatted (user-friendly) date time format for the JS widget.
	 * Will be used by moment.js for instance.
	 *
	 * @return string
	 */
	public function GetDateTimeFormatForJSWidget()
	{
		$oDateTimeFormat = AttributeDateTime::GetFormat();
		return $oDateTimeFormat->ToMomentJS();
	}

	/**
	 * @inheritdoc
	 */
	public function GetSubBlocks()
	{
		$aSubBlocks = array();

		foreach($this->GetCaseLogTabsEntryForms() as $sCaseLogId => $oCaseLogEntryForm) {
			$aSubBlocks[$oCaseLogEntryForm->GetId()] = $oCaseLogEntryForm;
		}

		return $aSubBlocks;
	}

	/**
	 * @see static::$bShowMultipleEntriesSubmitConfirmation
	 * @return $this
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \MySQLException
	 */
	protected function ComputedShowMultipleEntriesSubmitConfirmation()
	{
		// Note: Test on a string is necessary as we can only store strings from the JS API, not booleans.
		// Note 2: Do not invert the test to "=== 'true'" as it won't work. Default value is a bool ("true"), values from the DB are strings (true|false)
		$this->bShowMultipleEntriesSubmitConfirmation = appUserPreferences::GetPref('activity_panel.show_multiple_entries_submit_confirmation', static::DEFAULT_SHOW_MULTIPLE_ENTRIES_SUBMI_CONFIRMATION) !== 'false';
		return $this;
	}
}