-- Only needed on databases with entries saved before release 2.1.0.
-- validateSAMobile() used to store numbers as "+27 821234567"; it now stores "+27821234567"
-- (the same format as the seeder and the entries-page search), so remove the space.
use `connectionyou`;

UPDATE `users`
    SET `phone_number` = REPLACE(`phone_number`, ' ', '')
    WHERE `phone_number` LIKE '+27 %';
