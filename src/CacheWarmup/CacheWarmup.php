<?php

namespace Oneup\Contao\CacheWarmup;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\RequestException;

class CacheWarmup extends \Backend implements \executable
{
    public function __construct()
    {
        parent::__construct();

        // Add custom stylesheet
        if ('BE' === TL_MODE) {
            $GLOBALS['TL_CSS'][] = 'system/modules/cache-warmup/assets/css/module.css';

            if ($this->isActive()) {
                $GLOBALS['TL_JAVASCRIPT'][] = 'assets/jquery/core/' . $GLOBALS['TL_ASSETS']['JQUERY'] . '/jquery.min.js|static';
                $GLOBALS['TL_JAVASCRIPT'][] = 'system/modules/cache-warmup/assets/js/jquery.ajaxQueue.js|static';
            }
        }
    }

    /**
     * Return true if the module is active
     * @return boolean
     */
    public function isActive()
    {
        return (\Input::get('act') == 'cache_warmup');
    }

    public function run()
    {
        // Warmup the cache
        if (\Environment::get('isAjaxRequest') && null !== \Input::get('cacheUrl')) {
            $url = preg_replace('/&#(.)*/', '', \Input::get('cacheUrl'));

            $jar = new CookieJar();
            $jar->clear();

            // TODO: implement frontend user
            // $this->setCookie('FE_USER_AUTH', $strHash, ($time - 86400), null, null, false, true);
            // $this->setCookie('FE_AUTO_LOGIN', \Input::cookie('FE_AUTO_LOGIN'), ($time - 86400), null, null, false, true);

            $mobileClient  = new Client();
            $desktopClient = new Client();

            try {
                $mobileClient->request('GET', $url, [
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (iPhone; U; CPU iPhone OS 4_3_3 like Mac OS X; en-us) AppleWebKit/533.17.9 (KHTML, like Gecko) Version/5.0.2 Mobile/8J2 Safari/6533.18.5',
                    ],
                    'cookies' => $jar,
                ]);

                $desktopClient->request('GET', $url, [
                    'cookies' => $jar,
                ]);

                http_response_code(200);
            } catch (RequestException $e) {
                http_response_code($e->getCode());
            }

            exit;
        }

        $time = time();
        $objTemplate                      = new \BackendTemplate('be_cache_warmup');
        $objTemplate->action              = ampersand(\Environment::get('request'));
        $objTemplate->cacheWarmupHeadline = $GLOBALS['TL_LANG']['tl_maintenance']['cacheWarmup'];
        $objTemplate->isActive            = $this->isActive();

        // Add the error message
        if ($_SESSION['REBUILD_CACHE_ERROR'] != '') {
            $objTemplate->cacheWarmupMessage = $_SESSION['REBUILD_CACHE_ERROR'];
            $_SESSION['REBUILD_CACHE_ERROR'] = '';
        }

        // Get the urls from page tree
        if (\Input::get('act') == 'cache_warmup') {
            $baseUrl  = \Environment::get('url').\Environment::get('path').'/';
            $objPages = \PageModel::findAll();

            $arrPages = [];
            $sitemaps = [];
            $arrXml = [];
            $objPages->reset();

            while (null !== $objPages && $objPages->next()) {
                $objPage = $objPages->current();

                if ($objPage->createSitemap && 'root' === $objPage->type) {
                    $sitemaps[] = $objPage->sitemapName;
                }

                $arrPages[] = $baseUrl.$this->generateFrontendUrl($objPage->row());
            }

            foreach ($sitemaps as $sitemap) {
                $filename = sprintf("share/%s.xml", $sitemap);
                $file = new \File($filename, true);

                if ($file->exists()) {
                    $xml = simplexml_load_string($file->getContent());

                    foreach ($xml->children() as $page) {
                        $arrXml[] = (string) $page->loc;
                    }
                }
            }

            $arrPages = array_values(array_filter(array_unique(array_merge($arrPages, $arrXml))));

            // Check the request token (see #4007)
            if (!isset($_GET['rt']) || !\RequestToken::validate(\Input::get('rt'))) {
                $this->Session->set('INVALID_TOKEN_URL', \Environment::get('request'));
                $this->redirect('contao/confirm.php');
            }

            // HOOK: take additional cacheable pages
            if (isset($GLOBALS['TL_HOOKS']['getCacheablePages']) && is_array($GLOBALS['TL_HOOKS']['getCacheablePages'])) {
                foreach ($GLOBALS['TL_HOOKS']['getCacheablePages'] as $callback) {
                    $this->import($callback[0]);
                    $arrPages = $this->$callback[0]->$callback[1]($arrPages);
                }
            }

            // Return if there are no pages to cache
            if (empty($arrPages)) {
                $_SESSION['REBUILD_CACHE_ERROR'] = $GLOBALS['TL_LANG']['tl_maintenance']['noCacheable'];
                $this->redirect($this->getReferer());
            }

            $this->import('Automator');

            // Truncate the page cache
            $this->Automator->purgePageCache();

            // Hide unpublished elements
            $this->setCookie('FE_PREVIEW', 0, ($time - 86400));

            // Calculate the hash
            $strHash = sha1(session_id().(!\Config::get('disableIpCheck') ? \Environment::get('ip') : '').'FE_USER_AUTH');

            // Remove old sessions
            $this->Database
                ->prepare("DELETE FROM tl_session WHERE tstamp<? OR hash=?")
                ->execute(($time - \Config::get('sessionTimeout')), $strHash);

            // TODO: apply to guzzle
            // Log in the front end user
            if (is_numeric(\Input::get('user')) && \Input::get('user') > 0) {
                // Insert a new session
                // $this->Database->prepare("INSERT INTO tl_session (pid, tstamp, name, sessionID, ip, hash) VALUES (?, ?, ?, ?, ?, ?)")
                // ->execute(\Input::get('user'), $time, 'FE_USER_AUTH', session_id(), \Environment::get('ip'), $strHash);

                // Set the cookie
                // $this->setCookie('FE_USER_AUTH', $strHash, ($time + \Config::get('sessionTimeout')), null, null, false, true);
            }

            // Log out the front end user
            else {
                // Unset the cookies
                // $this->setCookie('FE_USER_AUTH', $strHash, ($time - 86400), null, null, false, true);
                // $this->setCookie('FE_AUTO_LOGIN', \Input::cookie('FE_AUTO_LOGIN'), ($time - 86400), null, null, false, true);
            }

            $strBuffer = '';
            $rand      = rand();

            // Display the pages
            for ($i = 0, $c = count($arrPages); $i<$c; $i++) {
                // Use StringUtil class when used Contao version is minimum 3.5.1
                // see https://github.com/contao/core-bundle/issues/309
                if (version_compare(VERSION.'.'.BUILD, '3.5.5', '>=')) {
                    $strBuffer .= '<span class="page-url" data-url="'.$arrPages[$i].'#'.$rand.$i.'">'.\StringUtil::substr($arrPages[$i], 100).'</span><br>';
                } else {
                    $strBuffer .= '<span class="page-url" data-url="'.$arrPages[$i].'#'.$rand.$i.'">'.\String::substr($arrPages[$i], 100).'</span><br>';
                }

                unset($arrPages[$i]); // see #5681
            }

            $objTemplate->content             = $strBuffer;
            $objTemplate->note                = $GLOBALS['TL_LANG']['tl_maintenance']['cacheWarmupNote'];
            $objTemplate->loading             = $GLOBALS['TL_LANG']['tl_maintenance']['cacheWarmupLoading'];
            $objTemplate->complete            = $GLOBALS['TL_LANG']['tl_maintenance']['cacheWarmupComplete'];
            $objTemplate->cacheWarmupContinue = $GLOBALS['TL_LANG']['MSC']['continue'];
            $objTemplate->theme               = \Backend::getTheme();
            $objTemplate->isRunning           = true;

            return $objTemplate->parse();
        }

        $arrUser = array('' => '-');

        // Get active front end users
        $objUser = $this->Database->execute("SELECT id, username FROM tl_member WHERE disable!=1 AND (start='' OR start<$time) AND (stop='' OR stop>$time) ORDER BY username");

        while ($objUser->next()) {
            $arrUser[$objUser->id] = $objUser->username.' ('.$objUser->id.')';
        }

        // Default variables
        $objTemplate->user              = $arrUser;
        $objTemplate->cacheWarmupLabel  = $GLOBALS['TL_LANG']['tl_maintenance']['cacheFrontendUser'][0];
        $objTemplate->cacheWarmupHelp   = (\Config::get('showHelp') && strlen($GLOBALS['TL_LANG']['tl_maintenance']['cacheFrontendUser'][1])) ? $GLOBALS['TL_LANG']['tl_maintenance']['cacheFrontendUser'][1] : '';
        $objTemplate->cacheWarmupSubmit = $GLOBALS['TL_LANG']['tl_maintenance']['cacheWarmupSubmit'];

        return $objTemplate->parse();
    }

    public function getCacheKey($strCacheKey)
    {
        /** @var \PageModel $objPage */
        global $objPage;

        // do not modify, when mobile layout is set explicitely
        if ($objPage->mobileLayout > 0) {
            return $strCacheKey;
        }

        // modify CacheKey due to system/modules/core/classes/FrontendTemplate.php#234-244
        if (\Input::cookie('TL_VIEW') == 'mobile' || \Environment::get('agent')->mobile) {
            $strCacheKey .= '.mobile';
        }

        return $strCacheKey;
    }
}
