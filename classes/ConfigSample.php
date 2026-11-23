<?php

class Config {

    public string $domain_name = 'db.marbletrack3.com';  // used for cookies
    public string $cookie_name = 'mt3login'; // used for cookies
    public int $cookie_lifetime = 60 * 60 * 24 * 30; // 30 days
    public string $app_path = '/home/barefoot_rob/db.marbletrack3.com';
    public string $todos_path = '/home/barefoot_rob/db.marbletrack3.com/todos'; // where todo markdown files are stored

    public string $dbHost = "localhost";
    public string $dbUser = "";
    public string $dbPass = "";
    public string $dbName = "";
}
