<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\LoginPKI;

use Piwik\Settings\SystemSetting;
use Piwik\Settings\UserSetting;

/**
 * Defines Settings for LoginPKI.
 *
 * Usage like this:
 * $settings = new Settings('LoginPKI');
 * $settings->autoRefresh->getValue();
 * $settings->metric->getValue();
 *
 */
class Settings extends \Piwik\Plugin\Settings
{
    /** @var SystemSetting */
    public $viewableUsers;

    /** @var SystemSetting */
    public $useGovportGroups;

    /** @var SystemSetting */
    public $govportGroup;

    /** @var SystemSetting */
    public $govportProject;

    protected function init()
    {
        $this->createUseGovportGroupsSetting();
        $this->createGovportGroup();
        $this->createGovportProject();
        $this->createViewableUsersSetting();
    }

    private function createViewableUsersSetting()
    {
        $this->viewableUsers = new SystemSetting('viewableUsers', 'List of Viewable Users');
        $this->viewableUsers->readableByCurrentUser = true;

        $this->viewableUsers->uiControlType = static::CONTROL_TEXTAREA;
        $this->viewableUsers->description   = 'Enter UIDs of viewable users, separated by carriage returns. Entering no UIDs means all users will have access.';

        $this->addSetting($this->viewableUsers);
    }

    private function createUseGovportGroupsSetting()
    {
        $this->useGovportGroups = new SystemSetting('useGovportGroups', 'Use Govport Groups');
        $this->useGovportGroups->type  = static::TYPE_BOOL;
        $this->useGovportGroups->uiControlType = static::CONTROL_CHECKBOX;
        $this->useGovportGroups->description   = 'If enabled, the Govport will be used to determine viewable user status.';
        $this->useGovportGroups->defaultValue  = false;
        $this->useGovportGroups->readableByCurrentUser = true;

        $this->addSetting($this->useGovportGroups);
    }

    private function createGovportProject() {
        $this->govportProject = new SystemSetting('govportProject', 'Govport Project for Viewable Users Group');
        $this->govportProject->type  = static::TYPE_STRING;
        $this->govportProject->description   = 'Enter the Govport Project that contains the Govport Group.';
        $this->govportProject->readableByCurrentUser = true;

        $this->addSetting($this->govportProject);
    }

    private function createGovportGroup() {
        $this->govportGroup = new SystemSetting('govportGroup', 'Govport Group for Viewable Users');
        $this->govportGroup->type  = static::TYPE_STRING;
        $this->govportGroup->description   = 'Enter the Govport Group to use to check if connecting user is authorized to view.';
        $this->govportGroup->readableByCurrentUser = true;

        $this->addSetting($this->govportGroup);
    }
}
