## Chronolabs Cooperative Presents
# Robots API in PHP for Social networks API's
## Author: Dr. Simon Antony Roberts <simon@snails.email>
### Robots for Twitter + Other Later Social Media Platforms API's
This is a ROBOT(s) API Service there is no calling or REST API functions on this API from here it is an automated service. This help only provide current tasks that have been described by the robot's status lines on logging! Howevere there is in the /include path the following configuration files...

# Configuring for Twitter
In the /include/robots path you create a username for the name of the folder ie. @OpenRend, then in there is a number of *.diz and one /include/robots/@OpenRend/settings.php which has the following data in it...

## Example settings.php in configuration
This is the example settings.php in each configuration bin of usernames, this is for @OpenRend:~

    <?php
    
        return array(   'twitter'      =>  array(   'consumer_key'                  =>  'oclbkcn7QZ5fKBCZw2xCK0QqH',
                                                    'consumer_secret'               =>  'q0EHWHcffyddjyhh0UYIyHtZwkwDTjPiRXukwmefL8OzOjjyTj',
                                                    'oauth_access_token'            =>  '2836500978-WqXCo7D3vMKHaYKKLdVF5o6ev39QCZMqhSJ9Sq6',
                                                    'oauth_access_token_secret'     =>  'n4RHXBolb9dM0kP5GWbzzW5YqGxTDdEDuNnQ4OwyKv2mw',
                                                    'username'                      =>  'OpenRend'
                                       ),
                        'robots'       =>   array(  'twitter'           =>  array('retweeter', 'replier', 'newfollowers', 'thankfollowers', 'fairwellfollowers')
                                    ),
                        'thankfollowers'=> array(   'sesssion'                      =>  11,
                                                    'seconds'                       =>  8444
                                                ),
                        'fairwellfollowers'=> array('sesssion'                      =>  11,
                                                    'seconds'                       =>  8444
                                                )
                            
               );
                        
        ?>
