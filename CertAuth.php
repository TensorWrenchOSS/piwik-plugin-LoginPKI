<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\LoginPKI;

use Exception;
use Piwik\AuthResult;
use Piwik\Db;
use Piwik\Date;
use Piwik\Config;
use Piwik\Plugins\UsersManager\Model;
use Piwik\Session;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;
use Piwik\Plugins\Login\API as LoginAPI;
use Piwik\Plugins\ClientCertificates\API as ClientCertificatesAPI;

/**
 *
 */
class CertAuth implements \Piwik\Auth
{
    protected $login = null;
    protected $token_auth = null;
    protected $md5Password = null;
    protected $email = null;
    protected $alias = null;
    protected $userDN = null;

    /**
     * Authentication module's name, e.g., "Login"
     *
     * @return string
     */
    public function getName()
    {
        return 'LoginPKI';
    }

    /**
     * Authenticates user
     *
     * @return AuthResult
     */
    public function authenticate()
    {
        $model = new Model();
        $user = $model->getUser($this->login);
        
        if(!$user) {
            $user  = $model->getUserByTokenAuth($this->token_auth);

            if(!$user) {
                if($this->getViewableUserStatus() || $this->getSuperUserStatus()) {
                    \Piwik\Log::info("Creating user ". $this->login);

                    $model->addUser($this->login, $this->getTokenAuthSecret(), $this->email, $this->alias, $this->token_auth, Date::now()->getDatetime());
                    $site_id = $this->getDefaultSiteId();
                    $model->addUserAccess($this->login, "view", [$site_id]);

                    $user = $model->getUser($this->login);
                } else {
                    $loginAPI = LoginAPI::getInstance();
                    $loginAPI->setLoginMessage("You do not have access to view. Please contact the AppsMall team for access.");
                    return new AuthResult(AuthResult::FAILURE, $this->login, $this->token_auth);
                }

            }
        }

        $this->login = $user['login'];
        $is_superuser = $this->getSuperUserStatus();
        $model->setSuperUserAccess($this->login, $is_superuser);

        $accessCode = $user['superuser_access'] ? AuthResult::SUCCESS_SUPERUSER_AUTH_CODE : AuthResult::SUCCESS;

    	return new AuthResult($accessCode, $this->login, $this->token_auth);
    }

    private function getViewableUserStatus()
    {
        $is_viewable_user = false;
        $settings = new Settings();
        $use_govport_groups = $settings->useGovportGroups->getValue();
        $group = $settings->govportGroup->getValue();
        $project = $settings->govportProject->getValue();

        if($use_govport_groups && $group != "" && $project != "") {
            \Piwik\Log::debug("Using Govport Groups to get viewable status");
            $clientCertificateAPI = ClientCertificatesAPI::getInstance();

            $result = $clientCertificateAPI->queryGovportGroup($this->userDN, $group, $project);

            if($result) {
                $is_viewable_user = $this->getProperty($result, 'isMember');
                \Piwik\Log::debug("User [".$this->login."] viewable [".Array(false => 'false', true => 'true')[$is_viewable_user] ."]");
            } else {
                $loginAPI = LoginAPI::getInstance();
                $loginAPI->setErrorMessage("Could not verify user against group authorization service");
            }

        } else {
            $viewable_users_string = $settings->viewableUsers->getValue();
            $viewable_users = explode("\n", $viewable_users_string);

            foreach ($viewable_users as $viewable_user) {
                if(trim($viewable_user) == $this->login) {
                    $is_viewable_user = true;
                }
            }
            
            if($viewable_users_string == "") {
                $is_viewable_user = true;
                \Piwik\Log::debug("No viewable users list");
            } else if($is_viewable_user) {
                \Piwik\Log::debug("User [".$this->login."] is on viewable list");
            } else {
                \Piwik\Log::debug("User [".$this->login."] is not on viewable list");
            }
        }


        return $is_viewable_user;
    }

    private function getSuperUserStatus() 
    {
        $is_superuser = false;
        $loginConfig = Config::getInstance()->LoginPKI;

        if($loginConfig && array_key_exists('superusers',$loginConfig)) {
            $superusers = $loginConfig['superusers'];
            
            foreach ($superusers as $superuser) {
                if($superuser == $this->login) {
                    $is_superuser = true;
                }
            }

            return $is_superuser;
        } else {
            throw new Exception("Administrator must define a super user in config.ini.php file.<br/><br/>[LoginPKI]<br/>superusers[] = &lt;user&gt;");
        }
    }

    private function getDefaultSiteId() 
    {
        $loginConfig = Config::getInstance()->LoginPKI;
        if($loginConfig && array_key_exists('default_site_id',$loginConfig)) {
            return $loginConfig['default_site_id'];
        } else {
            throw new Exception("Administrator must define a default site id in config.ini.php file.<br/><br/>[LoginPKI]<br/>default_site_id = &lt;site-id&gt;");
        }
    }

    private function getProperty($data, $property) {
        if(property_exists($data, $property)) {
            return $data->{$property};
        } else {
            return null;
        }
    }

    /**
     * Returns the login of the user being authenticated.
     *
     * @return string
     */
    public function getLogin()
    {
        return $this->login;
    }

    /**
     * Accessor to set login name
     *
     * @param string $login user login
     */
    public function setLogin($login)
    {
        $this->login = $login;
    }


    public function getEmail() 
    {
        return $this->email;
    }

    public function setEmail($email) 
    {
        $this->email = $email;
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function setAlias($alias) 
    {
        $this->alias = $alias;
    }

    public function getUserDN()
    {
        return $this->userDN;
    }

    public function setUserDN($dn) {
        $this->userDN = $dn;
    }

    /**
     * Returns the secret used to calculate a user's token auth.
     *
     * @return string
     */
    public function getTokenAuthSecret()
    {
        return $this->md5Password;
    }

    /**
     * Accessor to set authentication token
     *
     * @param string $token_auth authentication token
     */
    public function setTokenAuth($token_auth)
    {
        $this->token_auth = $token_auth;
    }

    /**
     * Sets the password to authenticate with.
     *
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->md5Password = md5($password);
    }

    /**
     * Sets the password hash to use when authentication.
     *
     * @param string $passwordHash The password hash.
     * @throws Exception if $passwordHash does not have 32 characters in it.
     */
    public function setPasswordHash($passwordHash)
    {
        if (strlen($passwordHash) != 32) {
            throw new Exception("Invalid hash: incorrect length " . strlen($passwordHash));
        }

        $this->md5Password = $passwordHash;
    }
}