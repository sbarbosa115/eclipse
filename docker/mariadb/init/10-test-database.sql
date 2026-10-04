-- The test suite runs against app_test (doctrine.yaml sets dbname_suffix in when@test), and ParaTest may add a
-- token: app_test1, app_test2… A plain user cannot create databases, so grant the whole pattern up front.
GRANT ALL PRIVILEGES ON `app\_test%`.* TO 'app'@'%';
FLUSH PRIVILEGES;
