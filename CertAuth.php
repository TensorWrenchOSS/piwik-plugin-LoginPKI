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
                \Piwik\Log::info("Creating  user ". $this->login);
                $password = md5($this->login . ":amlmetrics");

                $model->addUser($this->login, $password, $this->email, $this->alias, $this->token_auth, Date::now()->getDatetime());
                $site_id = $this->getDefaultSiteId();
                $model->addUserAccess($this->login, "view", [$site_id]);

                $user = $model->getUser($this->login);
            }
        }

        $this->login = $user['login'];
        $is_superuser = $this->getSuperUserStatus();
        $model->setSuperUserAccess($this->login, $is_superuser);

        $accessCode = $user['superuser_access'] ? AuthResult::SUCCESS_SUPERUSER_AUTH_CODE : AuthResult::SUCCESS;

    	return new AuthResult($accessCode, $this->login, $this->token_auth);
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
        $this->setPassword(null);
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
        $this->md5Password = md5("CertAuth:".$this->login);
    }

    /**
     * Sets the password hash to use when authentication.
     *
     * @param string $passwordHash The password hash.
     * @throws Exception if $passwordHash does not have 32 characters in it.
     */
    public function setPasswordHash($passwordHash)
    {
        $this->md5Password = md5("CertAuth:".$login);
    }
}