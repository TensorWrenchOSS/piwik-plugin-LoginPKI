<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\LoginPKI;

use Piwik\Piwik;
use Piwik\Config;
use Piwik\Cookie;
use Piwik\ProxyHttp;
use Piwik\Session;
use Piwik\Plugins\ClientCertificates\API as ClientCertificatesAPI;

/**
 */
class LoginPKI extends \Piwik\Plugin {
	
	public function getListHooksRegistered() {
        return array(
            'Request.initAuthenticationObject' => 'initAuthenticationObject',
            // 'User.isNotAuthorized'             => 'noAccess',
            'API.Request.authenticate'         => 'ApiRequestAuthenticate',
            'UsersManager.allowPasswordChange' => 'allowPasswordChange',
            'View.allowSignout'                => 'allowSignout'
        );
    }

    public function allowPasswordChange(&$allowPasswordChange) {
        $allowPasswordChange = false;
    }

    public function allowSignout(&$allowSignout) {
        $allowSignout = false;
    }

    public function initAuthenticationObject($activateCookieAuth = false) {
        \Piwik\Log::debug("PKI AUTHENTICATION");

        $clientCertificateAPI = ClientCertificatesAPI::getInstance();
        $dn = $clientCertificateAPI->getUserDN();

        if($dn != null) {
            $auth = new CertAuth();
            $previousAuth = \Piwik\Registry::get('auth');
            \Piwik\Registry::set('auth', $auth);

            if(!$this->initAuthenticationFromCookie($auth, $activateCookieAuth)) {
                $result = $clientCertificateAPI->queryGovport($dn);

                if($result) {

                    $username = $this->getProperty($result, 'uid');
                    $fullname = $this->getProperty($result, 'fullName');
                    $email = $this->getProperty($result, 'email'); 
                    $firstname = $this->getProperty($result, 'firstName');
                    $lastname = $this->getProperty($result, 'lastName');

                    $agency = null;
                    if(property_exists($result, 'grantBy')) {
                        $agency = $result->{'grantBy'}[0];
                    }
                        
                    if($agency == null)
                    {
                        if(property_exists($result, 'organizations')) {
                            $agency = $result->{'organizations'}[0];
                        }

                        if($agency == null) {
                            $agency = 'N/A';
                        }
                    }

                    \Piwik\Log::debug("Response from GOVPORT:");
                    \Piwik\Log::debug("user = ".$username);
                    \Piwik\Log::debug("fullname = ".$fullname);
                    \Piwik\Log::debug("email = ".$email);
                    \Piwik\Log::debug("firstname = ".$firstname);
                    \Piwik\Log::debug("lastname = ".$lastname);
                    \Piwik\Log::debug("agency = ".$agency);

                    $auth->setLogin($username);
                    $auth->setTokenAuth(md5($username . $auth->getTokenAuthSecret()));
                    $auth->setEmail($email);
                    $auth->setAlias($this->getAlias($firstname, $lastname, $fullname));

                    $authResult = $auth->authenticate();
                    if($authResult->wasAuthenticationSuccessful()) {
                        Session::regenerateId();

                        //Create Cookie
                        $authCookieExpiry = 0; 
                        $authCookieName = Config::getInstance()->General['login_cookie_name'];
                        $authCookiePath = Config::getInstance()->General['login_cookie_path'];

                        $cookie = new Cookie($authCookieName, $authCookieExpiry, $authCookiePath);
                        $cookie->set('login', $authResult->getIdentity());
                        $cookie->set('token_auth', md5($username . $auth->getTokenAuthSecret()));
                        $cookie->setSecure(ProxyHttp::isHttps());
                        $cookie->setHttpOnly(true);
                        $cookie->save();
                    }
                } else { 
                    \Piwik\Registry::set('auth', $previousAuth);
                    \Piwik\Log::debug("Could not contact authorization service. Falling back on standard auth.");
                }
            }
        } else {
            \Piwik\Log::debug("No certificate provided. Falling back on standard login mechanism.");
        }

    }

    public function ApiRequestAuthenticate($tokenAuth) {
        \Piwik\Registry::get('auth')->setLogin($login = null);
        \Piwik\Registry::get('auth')->setTokenAuth($tokenAuth);
    }

    private static function initAuthenticationFromCookie(\Piwik\Auth $auth, $activateCookieAuth)
    {
        if (self::isModuleIsAPI() && !$activateCookieAuth) {
            return false;
        }

        $authCookieName = Config::getInstance()->General['login_cookie_name'];
        $authCookieExpiry = 0;
        $authCookiePath = Config::getInstance()->General['login_cookie_path'];
        $authCookie = new Cookie($authCookieName, $authCookieExpiry, $authCookiePath);
        if ($authCookie->isCookieFound()) {
            $login = $authCookie->get('login');
            $tokenAuth = $authCookie->get('token_auth');
            
            $auth->setLogin($login);
            $auth->setTokenAuth($tokenAuth);

            return true;
        } else {
            return false;
        }
    }

    private static function isModuleIsAPI()
    {
        return Piwik::getModule() === 'API'
                && (Piwik::getAction() == '' || Piwik::getAction() == 'index');
    }

    private function getProperty($data, $property) {
        if(property_exists($data, $property)) {
            return $data->{$property};
        } else {
            return null;
        }
    }

    private function getAlias($firstname, $lastname, $fullname) {
        if($firstname == null || $lastname == null) {
            return $fullname;
        } else {
            return ucwords(strtolower($firstname)) . " " .  ucwords(strtolower($lastname));
        }
    }
}
