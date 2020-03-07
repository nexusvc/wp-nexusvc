# wp-nexusvc
Wordpress plugin for optimized tracking, lead post queueing and GravityForm enhancements

## Installation
- Upload plugin to wordpress plugins directory
- Update the dependencies using composer
```bash
/plugins/wp-nexusvc/$ composer update
```
- Activate the plugin
Upon activation, the plugin will automatically migrate a set of tables on the database and generate an env file for the nxvc phar application to utilize as well as create a symlink to the phar application at the ABSPATH of the wordpress install.

The symlink is used for supervisor to have a path to ensure the queue is running
- Configure the nexusvc integration settings on the settings page
- Select which gravity forms to enable post success hooks

## Requirements
Requires supervisor service installed or accessible to docker instance for queueing to work

