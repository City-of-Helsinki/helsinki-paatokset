# Lupapiste RSS

Lupapiste RSS block shows the RSS feed from lupapiste. It's set to be visible in a specic url, since it needs to be visible on a taxonomy page. A view template is created for the lupapiste feed to mimic the similar structure of taxonomy term view.

As a default the block is shown on /kuulutukset-ja-ilmoitukset/rakennusvalvonnan-lupapaatokset but it can be changed with drush command

``
drush state:set lupapiste '/new-value-here'
``

To check what value is set

``
drush state:get lupapiste
``