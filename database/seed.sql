USE rota_do_amor;

INSERT INTO admins (name,email,password,role,status,created_at,updated_at)
VALUES ('Super Admin','admin@rotadoamor.mz','$2y$10$QQq7P4ElG29E6Ytv8PHdPu2jX4rAtA3ahUBygQzf1sY4pVH4A8M7a','super_admin','active',NOW(),NOW());

INSERT INTO site_settings (setting_key,setting_value,value_type,updated_at) VALUES
('activation_price','100','int',NOW()),
('monthly_subscription_price','40','int',NOW()),
('boost_price','25','int',NOW()),
('boost_duration_hours','24','int',NOW()),
('email_verification_required','true','bool',NOW()),
('allow_chat_only_after_match','true','bool',NOW());
