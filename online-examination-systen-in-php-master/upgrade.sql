-- ============================================================
-- upgrade.sql  –  Run ONCE on your XAMPP database "project"
-- Capstone: Secure Online Examination Paper Distribution System
-- ============================================================

-- 1. Widen password columns for salted SHA-256  (salt:hash = 32+1+64 = 97 chars)
ALTER TABLE `user`  MODIFY `password` VARCHAR(150) NOT NULL;
ALTER TABLE `admin` MODIFY `password` VARCHAR(150) NOT NULL;

-- 2. Add scheduling & status columns to quiz
ALTER TABLE `quiz`
  ADD COLUMN `start_time` DATETIME NULL AFTER `tag`,
  ADD COLUMN `status`     VARCHAR(20) NOT NULL DEFAULT 'locked' AFTER `start_time`;

-- For the existing sample quizzes set status='unlocked' so they remain usable
-- until you run migrate_quizzes.php to re-encrypt them properly.
UPDATE `quiz` SET `status` = 'unlocked' WHERE `status` = 'locked';

-- 3. Secure cipher store
CREATE TABLE IF NOT EXISTS `quiz_secure` (
  `eid`        VARCHAR(50)  NOT NULL PRIMARY KEY,
  `cipher_b64` LONGTEXT     NOT NULL,
  `iv_b64`     VARCHAR(100) NOT NULL,
  `sha256`     CHAR(64)     NOT NULL,
  `created`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- 4. Activity / audit log
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id`      INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `email`   VARCHAR(50)  DEFAULT NULL,
  `action`  VARCHAR(100) DEFAULT NULL,
  `eid`     VARCHAR(50)  DEFAULT NULL,
  `ip`      VARCHAR(45)  DEFAULT NULL,
  `created` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
