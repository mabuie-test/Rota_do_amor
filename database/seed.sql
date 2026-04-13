USE rota_do_amor;

INSERT INTO admins (name,email,password,role,status,created_at,updated_at)
VALUES ('Super Admin','admin@rotadoamor.mz','$2y$10$QQq7P4ElG29E6Ytv8PHdPu2jX4rAtA3ahUBygQzf1sY4pVH4A8M7a','super_admin','active',NOW(),NOW());

INSERT INTO site_settings (setting_key,setting_value,value_type,updated_at) VALUES
('activation_price','100','int',NOW()),
('monthly_subscription_price','40','int',NOW()),
('boost_price','25','int',NOW()),
('boost_duration_hours','24','int',NOW()),
('email_verification_required','true','bool',NOW()),
('allow_chat_only_after_match','true','bool',NOW()),
('safe_dates_premium_guard_enabled','true','bool',NOW()),
('safe_dates_free_daily_limit','5','int',NOW()),
('safe_dates_premium_daily_limit','10','int',NOW()),
('safe_dates_verified_only_requires_identity','true','bool',NOW()),
('safe_dates_max_open_free','2','int',NOW()),
('safe_dates_max_open_premium','5','int',NOW()),
('daily_route_reward_boost_hours','2','int',NOW()),
('daily_route_reward_badge_type','constancia_diaria','string',NOW()),
('daily_route_reward_boost_hours_premium','3','int',NOW()),
('daily_route_streak_bonus_threshold','7','int',NOW()),
('daily_route_streak_bonus_boost_hours','1','int',NOW()),
('daily_route_target_discover_active','8','int',NOW()),
('daily_route_target_discover_default','5','int',NOW()),
('daily_route_target_feed_interactions','2','int',NOW()),
('daily_route_target_premium_momentum','1','int',NOW());
