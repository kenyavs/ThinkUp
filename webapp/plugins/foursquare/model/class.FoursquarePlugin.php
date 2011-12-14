<?php
/**
 *
 * webapp/plugins/foursquare/model/class.FoursquarePlugin.php
 *
 * LICENSE:
 *
 * This file is part of ThinkUp (http://thinkupapp.com).
 *
 * ThinkUp is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 2 of the License, or (at your option) any
 * later version.
 *
 * ThinkUp is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with ThinkUp.  If not, see
 * <http://www.gnu.org/licenses/>.
 * 
 *
 * Foursquare Plugin
 *
 * 
 *
 * Copyright (c) 2011 Aaron Kalair
 * 
 * @author Aaron Kalair <aaronkalair[at]gmail[dot]com>
 * @license http://www.gnu.org/licenses/gpl.html
 * @copyright 2011 Aaron Kalair
 */

class FoursquarePlugin extends Plugin implements CrawlerPlugin, DashboardPlugin, PostDetailPlugin {

    public function __construct($vals=null) {
        // Pass the values to the parents constructor
        parent::__construct($vals);
        // Set the foldername to foursquare
        $this->folder_name = 'foursquare';
        // Set the client secret
        $this->addRequiredSetting('foursquare_client_secret');
        // Set the client id
	    $this->addRequiredSetting('foursquare_client_id');
    }

    public function activate() {
	
    }

    public function deactivate() {
	
    }

    public function renderConfiguration($owner) {
        // Create a new controller for the plugin
        $controller = new FoursquarePluginConfigurationController($owner, 'foursquare');
        return $controller->go();
    }
    
    /* Go to foursquare and get the users data */
    public function crawl() {
        // Create a logger
        $logger = Logger::getInstance();
        // Get a instance configuration
        $config = Config::getInstance();
        // Create an instance DAO
        $instance_dao = DAOFactory::getDAO('InstanceDAO');
        // Create an owner instance DAO
        $owner_instance_dao = DAOFactory::getDAO('OwnerInstanceDAO');
        // Create an owner DAO
        $owner_dao = DAOFactory::getDAO('OwnerDAO');
        
        // Get a plugin option DAO
        $plugin_option_dao = DAOFactory::GetDAO('PluginOptionDAO');
        // Get a cached hash of plugin options 
        $options = $plugin_option_dao->getOptionsHash('foursquare', true); 
        // Get the email address of the logged in user
        $current_owner = $owner_dao->getByEmail(Session::getLoggedInUser());

        //crawl foursquare users
        $instances = $instance_dao->getAllActiveInstancesStalestFirstByNetwork('foursquare');
        
        // Check the client id and secret are set or we can't crawl
        if (isset($options['foursquare_client_id']->option_value)
        && isset($options['foursquare_client_secret']->option_value)) {
            // For each instance of foursquare on this install 
            foreach ($instances as $instance) {
                if (!$owner_instance_dao->doesOwnerHaveAccessToInstance($current_owner, $instance)) {
                    // Owner doesn't have access to this instance; let's not crawl it.
                    continue;
                }
                // Set the user name in the log
                $logger->setUsername(ucwords($instance->network) . ' | '.$instance->network_username );
                // Write to the log that we have started to collect data
                $logger->logUserSuccess("Starting to collect data for ".$instance->network_username."'s ".
                ucwords($instance->network), __METHOD__.','.__LINE__);
                
                // Get the OAuth tokens for this user
                $tokens = $owner_instance_dao->getOAuthTokens($instance->id);
                // Set the access token
                $access_token = $tokens['oauth_access_token'];
                // Update the last time we crawled
                $instance_dao->updateLastRun($instance->id);
                // Create a new crawler
                $crawler = new FoursquareCrawler($instance, $access_token);
                // Check the OAuth tokens we have are valid
                try {
                    $crawler->initializeInstanceUser($access_token, $current_owner->id);
                    // Get the data we want and store it in the database
                    $crawler->fetchInstanceUserCheckins();
                } catch (Exception $e) {
                    // Catch any errors that happen when we check the validity of the OAuth tokens
                    $logger->logUserError('EXCEPTION: '.$e->getMessage(), __METHOD__.','.__LINE__);
                }
                
                $instance_dao->save($crawler->instance, 0, $logger);
                // Tell the user crawling was sucessful
                $logger->logUserSuccess("Finished collecting data for ".$instance->network_username."'s ".
                ucwords($instance->network), __METHOD__.','.__LINE__);
            }
        }
	
    }
    
    /**
     * Get Dashboard menu
     * @param $instance Instance
     * @return array of MenuItem objects (Tweets, Friends, Followers, etc)
     */
    public function getDashboardMenuItems($instance) {
        // An array to store our list of menu items in
        $menus = array();

        // Set the view template to checkins.tpl
        $checkins_data_tpl = Utils::getPluginViewDirectory('foursquare').'checkins.tpl';
        // Add a checkins link to the list of pages on the left
        $checkins_menu_item = new MenuItem("checkins", "All the checkins", $checkins_data_tpl);
        
        // Get the data for our checkins link
        $checkins_menu_ds_1 = new Dataset("all_checkins", 'PostDAO', "getAllCheckins",
        array($instance->network_user_id, $instance->network));
        $checkins_menu_item->addDataset($checkins_menu_ds_1);

        // Add the checkins to our array of items
        $menus['posts'] = $checkins_menu_item;
        
        // Return the list of items we want to display
        return $menus;
    }
    
    /**
     * Get Post Detail menu
     * @param $post Post
     * @return array of Menu objects (Tweets, Friends, Followers, etc)
     */
    public function getPostDetailMenuItems($post){
        
    }
	
}