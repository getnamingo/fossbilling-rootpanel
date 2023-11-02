<?php
/**
 * Rootpanel registrar module for FOSSBilling (https://fossbilling.org/)
 *
 * Written in 2023 by Taras Kondratyuk (https://getnamingo.org)
 * Based on HopeBilling Rootpanel module (https://github.com/vityabond/hopebilling/blob/master/app/classes/domain/RootPanel.php)
 *
 * @license MIT
 */

class Registrar_Adapter_Rootpanel extends Registrar_AdapterAbstract
{
    public $config = array(
        'url'   => null,
        'login' => null,
        'apikey' => null,
    );
    
    public function __construct($options)
    {
        
        if(isset($options['url']) && !empty($options['url'])) {
            $this->config['url'] = $options['url'];
            unset($options['url']);
        } else {
            throw new Registrar_Exception('Domain registrar "Rootpanel" is not configured properly. Please update configuration parameter "URL" at "Configuration -> Domain registration".');
        }
        
        if(isset($options['login']) && !empty($options['login'])) {
            $this->config['login'] = $options['login'];
            unset($options['login']);
        } else {
            throw new Registrar_Exception('Domain registrar "Rootpanel" is not configured properly. Please update configuration parameter "Rootpanel Login" at "Configuration -> Domain registration".');
        }

        if(isset($options['apikey']) && !empty($options['apikey'])) {
            $this->config['apikey'] = $options['apikey'];
            unset($options['apikey']);
        } else {
            throw new Registrar_Exception('Domain registrar "Rootpanel" is not configured properly. Please update configuration parameter "Rootpanel API Key" at "Configuration -> Domain registration".');
        }
        
    }

    public function getTlds()
    {
        return array();
    }

    public static function getConfig()
    {
        return array(
            'label'     =>  'Manages domains on ResellerClub via API. ResellerClub requires your server IP in order to work. Login to the ResellerClub control panel (the url will be in the email you received when you signed up with them) and then go to Settings > API and enter the IP address of the server where FOSSBilling is installed to authorize it for API access.',
            'form'  => array(
                'url' => array('text', array(
                            'label' => 'URL. You can get this at Rootpanel control panel',
                            'description'=> 'Rootpanel URL'
                        ),
                     ),
                'login' => array('text', array(
                            'label' => 'Login. You can get this at Rootpanel control panel',
                            'description'=> 'Rootpanel Login'
                        ),
                     ),
                'apikey' => array('password', array(
                            'label' => 'API Key. You can get this at Rootpanel control panel',
                            'description'=> 'Rootpanel API Key',
                            'required' => false,
                        ),
                     ),
            ),
        );
    }

    public function isDomaincanBeTransferred(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Checking if domain can be transferred: ' . $domain->getName());
        return true;
    }

    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Checking domain availability: ' . $domain->getName());
		
        $params = array('command' => 'checkDomain', 'domain' => $domain->getName());
        $res = $this->send($params);

        if(isset($res['avail']) && $res['avail'] == '1'){
            return true;
        } else {
            return false;
        }
    }

    public function modifyNs(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Modifying nameservers: ' . $domain->getName());
        $this->getLog()->debug('Ns1: ' . $domain->getNs1());
        $this->getLog()->debug('Ns2: ' . $domain->getNs2());
        $this->getLog()->debug('Ns3: ' . $domain->getNs3());
        $this->getLog()->debug('Ns4: ' . $domain->getNs4());
		
        $ns1 = $domain->getNs1();
        $ns2 = $domain->getNs2();
        if($domain->getNs3())  {
            $ns3 = $domain->getNs3();
        }
        if($domain->getNs4())  {
            $ns4 = $domain->getNs4();
        }
		
        $params = array(
            'command'   => 'updateDNS',
            'domain'    => $domain->getName(),
            'defaultns' => '0',
            'ns1'       => $ns1,
            'ns2'       => $ns2,
            'ns3'       => $ns3,
            'ns4'       => $ns4,
        );

        $res = $this->send($params);

        if($res['status'] == 'SUCCESS'){
            return true;
        }
        return false;
    }

    public function transferDomain(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Transfering domain: ' . $domain->getName());
        $this->getLog()->debug('Epp code: ' . $domain->getEpp());

        $params = array(
            'command'   => 'transferDomain',
            'domain'    => $domain->getName(),
            'period'    => 1,
            'authcode' => $domain->getEpp(),
            'defaultns' => '0',
            'ns1'       => $domain->getNs1(),
            'ns2'       => $domain->getNs2(),
            'ns3'       => $domain->getNs3(),
            'ns4'       => $domain->getNs4(),
        );

        $res = $this->send($params);

        $this->getLog()->debug('RootPanel transfer result:'. json_encode($res));
        if($res['status'] == 'SUCCESS'){
            return true;
        }

        return false;
    }

    public function getDomainDetails(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Getting whois: ' . $domain->getName());

        if(!$domain->getRegistrationTime()) {
            $domain->setRegistrationTime(time());
        }
        if(!$domain->getExpirationTime()) {
            $years = $domain->getRegistrationPeriod();
            $domain->setExpirationTime(strtotime("+$years year"));
        }
        return $domain;
    }

    public function deleteDomain(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Removing domain: ' . $domain->getName());
        return false;
    }

    public function registerDomain(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Registering domain: ' . $domain->getName(). ' for '.$domain->getRegistrationPeriod(). ' years');
		
        list($reg_contact_id, $admin_contact_id, $tech_contact_id, $billing_contact_id) = $this->_getAllContacts($tld, $customer_id, $domain->getContactRegistrar());
		
        $params = array(
            'command'   => 'registerDomain',
            'domain'    => $domain->getName(),
            'period'    => $domain->getRegistrationPeriod(),
            'profileid' => $reg_contact_id,
            'defaultns' => '0',
            'ns1'       => $domain->getNs1(),
            'ns2'       => $domain->getNs2(),
            'ns3'       => $domain->getNs3(),
            'ns4'       => $domain->getNs4(),
        );

        $res = $this->send($params);

        $this->getLog()->debug('RootPanel reg result:'. json_encode($res));
        if($res['status'] == 'SUCCESS'){
            return true;
        }

        return false;
    }

    public function renewDomain(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Renewing domain: ' . $domain->getName());
		
        $params = array(
            'command'   => 'renewDomain',
            'domain'    => $domain->getName(),
            'period'    => 1
        );

        $res = $this->send($params);
        if($res['status'] == 'SUCCESS'){
            return true;
        }
        return false;
    }

    public function modifyContact(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Updating contact info: ' . $domain->getName());
        return true;
    }

    public function enablePrivacyProtection(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Enabling Privacy protection: ' . $domain->getName());
        return false;
    }

    public function disablePrivacyProtection(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Disabling Privacy protection: ' . $domain->getName());
        return false;
    }

    public function getEpp(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Retrieving domain transfer code: ' . $domain->getName());
        return false;
    }

    public function lock(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Locking domain: ' . $domain->getName());
        return false;
    }

    public function unlock(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Unlocking: ' . $domain->getName());
        return false;
    }
    
    public function send($params)
    {
        $req = '';

        $params["login"] = $this->config['login'];
        $params["apikey"] = $this->config['apikey'];

        while ( list($k,$v) = @each($params)) {
            $req = $req."$k=".urlencode($v)."&";
        }

        $fp = curl_init();
        curl_setopt($fp, CURLOPT_URL, $this->config['url']);
        curl_setopt($fp, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($fp, CURLOPT_POST, true);
        curl_setopt($fp, CURLOPT_POSTFIELDS, $req);
        curl_setopt($fp, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($fp, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($fp, CURLOPT_FAILONERROR, false);

        curl_setopt($fp, CURLOPT_TIMEOUT, 120);
        $result = curl_exec($fp);

        $this->getLog()->debug('RootPanel: '. $result);

        if (curl_errno($fp)) {
            curl_close($fp);
            return false;
        } else {
            curl_close($fp);

            $result = @unserialize($result);
            if (is_array($result) and count($result) > 1) {
                return $result;
            } else {
                return false;
            }
        }
    }
}
