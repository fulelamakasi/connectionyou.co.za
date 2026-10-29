use connectionyou;

ALTER TABLE `users`
    ADD COLUMN `id_number_encrypted` VARCHAR(255) DEFAULT NULL AFTER `id_number_hash`;
