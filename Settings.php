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

    protected function init()
    {
        $this->createDescriptionSetting();

    }

    private function createDescriptionSetting()
    {
        $this->viewableUsers = new SystemSetting('viewableUsers', 'List of Viewable Users');
                $this->viewableUsers->readableByCurrentUser = true;

        $this->viewableUsers->uiControlType = static::CONTROL_TEXTAREA;
        $this->viewableUsers->description   = 'Enter UIDs of viewable users, separated by carriage returns. Entering no UIDs means all users will have access.';

        $this->addSetting($this->viewableUsers);
    }
}
