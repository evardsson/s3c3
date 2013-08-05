CREATE TABLE contexts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    client VARCHAR(20) NOT NULL,
    token VARCHAR(128),
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires TIMESTAMP,
    completed TIMESTAMP,
    usage_status SMALLINT DEFAULT 0,
    INDEX token_idx (token)
);
CREATE TABLE configs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ckey VARCHAR(40) NOT NULL UNIQUE,
    cval VARCHAR(100)
);
INSERT INTO configs (ckey, cval) VALUES
('token.expire', 90),('token.length', 64),('token.strength', 'maximum'),('token.delete_on_load', 1);
