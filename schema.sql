-- Choithram Memorial Hospital: community management platform
-- Schema plus sample content. No real user data.
--
-- HOW TO USE
--   1. Create an empty database (XAMPP: phpMyAdmin > New. On a web host: use the host's
--      MySQL Databases page, which gives you the real database name).
--   2. Select that database in phpMyAdmin, open Import, choose this file, click Import.
--   3. Sign up on the website, then make yourself admin with this query:
--        UPDATE users SET role = 'admin' WHERE email = 'your-email@example.com';
--
-- The four sample mentors below have invalid passwords on purpose, so nobody can log in as them.

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  phone VARCHAR(20) NOT NULL DEFAULT '',
  gender VARCHAR(10) NOT NULL DEFAULT '',
  date_of_birth DATE NULL,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  role ENUM('member','admin') NOT NULL DEFAULT 'member',
  status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  is_mentor TINYINT(1) NOT NULL DEFAULT 0,
  interests VARCHAR(255) NULL,
  bio TEXT NULL,
  profile_picture VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  category VARCHAR(50) NOT NULL,
  location VARCHAR(150) NOT NULL,
  event_date DATETIME NOT NULL,
  capacity INT NOT NULL DEFAULT 50,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS event_registrations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  user_id INT NOT NULL,
  status ENUM('registered','attended','cancelled') NOT NULL DEFAULT 'registered',
  registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  notes VARCHAR(255) NULL,
  UNIQUE KEY one_per_user (event_id, user_id),
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS resources (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  category VARCHAR(50) NOT NULL,
  type ENUM('guide','tutorial','checklist','video','pdf','link') NOT NULL DEFAULT 'guide',
  link VARCHAR(255) NULL,
  content TEXT NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS resource_progress (
  id INT AUTO_INCREMENT PRIMARY KEY,
  resource_id INT NOT NULL,
  user_id INT NOT NULL,
  completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY one_per_user (resource_id, user_id),
  FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS forum_topics (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  body TEXT NOT NULL,
  category VARCHAR(50) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS forum_replies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  topic_id INT NOT NULL,
  user_id INT NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (topic_id) REFERENCES forum_topics(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mentorships (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mentor_id INT NOT NULL,
  mentee_id INT NOT NULL,
  status ENUM('pending','active','declined','ended') NOT NULL DEFAULT 'pending',
  requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY one_pair (mentor_id, mentee_id),
  FOREIGN KEY (mentor_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (mentee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- Sample content
-- ---------------------------------------------------------------------------

INSERT IGNORE INTO users (full_name, email, phone, gender, date_of_birth, password, interests, bio, is_mentor) VALUES
('Aminata Kamara', 'aminata.kamara@example.com', '+23276000001', 'Female', '1988-03-12', '$2y$10$abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXY01', 'Maternal & Child Health, Nutrition', 'Registered nurse supporting mothers through pregnancy and the first year of their baby''s life.', 1),
('Mohamed Sesay', 'mohamed.sesay@example.com', '+23276000002', 'Male', '1985-07-21', '$2y$10$abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXY01', 'Chronic Conditions, Fitness', 'Health worker who helps members build simple daily habits for blood pressure, diabetes and fitness.', 1),
('Fatmata Bangura', 'fatmata.bangura@example.com', '+23276000003', 'Female', '1990-11-02', '$2y$10$abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXY01', 'Mental Wellbeing, General Health', 'Community counsellor focused on stress, sleep and everyday wellbeing.', 1),
('Ibrahim Conteh', 'ibrahim.conteh@example.com', '+23276000004', 'Male', '1983-05-30', '$2y$10$abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXY01', 'First Aid, Malaria & Infectious Diseases', 'First aid trainer and infection prevention volunteer.', 1);

INSERT INTO events (title, description, category, location, event_date, capacity) VALUES
('Malaria Prevention Workshop', 'Learn how to protect your family from malaria: nets, clearing standing water and when to get tested.', 'Malaria & Infectious Diseases', 'Main Hall, Freetown', TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 5 DAY), '10:00:00'), 60),
('Healthy Pregnancy and Antenatal Care Talk', 'A friendly talk for expectant mothers about antenatal visits, nutrition and danger signs.', 'Maternal & Child Health', 'Outpatient Wing', TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 9 DAY), '09:30:00'), 40),
('Blood Pressure and Diabetes Screening Day', 'Free basic screening with advice from our nurses on living well with chronic conditions.', 'Chronic Conditions', 'Main Hall, Freetown', TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 14 DAY), '08:30:00'), 80),
('Managing Stress: A Wellbeing Session', 'Simple breathing and coping techniques you can use every day.', 'Mental Wellbeing', 'Community Room', TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 18 DAY), '14:00:00'), 30),
('First Aid Basics Training', 'Hands-on practice for cuts, burns, nosebleeds and when to call for help.', 'First Aid', 'Training Room', TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 23 DAY), '10:00:00'), 25),
('Eating Well on a Budget: Cooking Demo', 'Our nutrition team shows tasty, affordable and healthy meals using local ingredients.', 'Nutrition', 'Community Kitchen', TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 28 DAY), '11:00:00'), 35),
('Community Fun Walk', 'A relaxed morning walk for all ages. Wear comfortable shoes and bring water.', 'Fitness', 'Hospital Gate', TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 35 DAY), '07:00:00'), 100),
('Child Immunisation Awareness Day', 'Find out about the childhood vaccine schedule and have your questions answered.', 'Maternal & Child Health', 'Outpatient Wing', TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 42 DAY), '09:00:00'), 50);

INSERT INTO resources (title, description, category, type, content) VALUES
('Preventing Malaria at Home', 'Simple steps to keep mosquitoes away from your family.', 'Malaria & Infectious Diseases', 'guide',
'Malaria is spread by mosquito bites, mostly at night.\n\nWhat you can do:\n- Sleep under a treated mosquito net every night and check it for holes.\n- Remove standing water around your home (buckets, old tyres, blocked drains) where mosquitoes breed.\n- Wear long sleeves in the evening and use repellent if you can.\n- If you or your child has a fever, get tested at a health facility quickly. Do not guess the cause.\n\nPregnant women and young children are at higher risk and should seek care early.'),
('The 20-Second Hand Washing Guide', 'Wash your hands the right way to stop germs spreading.', 'General Health', 'tutorial',
'Steps:\n1. Wet your hands with clean water.\n2. Apply soap and rub your palms together.\n3. Rub the backs of your hands, between your fingers and under your nails.\n4. Keep scrubbing for at least 20 seconds.\n5. Rinse well and dry with a clean cloth or air dry.\n\nWash your hands before eating or preparing food, after using the toilet, after caring for someone who is sick, and after coughing or sneezing.'),
('Healthy Eating on a Budget', 'Nutritious meals do not have to be expensive.', 'Nutrition', 'guide',
'Tips:\n- Choose local, seasonal vegetables and fruit. They are fresher and cheaper.\n- Get protein from beans, groundnuts, eggs and fish.\n- Choose whole grains and tubers such as brown rice and cassava.\n- Limit sugary drinks and fried snacks.\n- Cook at home when you can and store food safely and covered.\n- Drink plenty of clean water through the day.'),
('Managing Everyday Stress', 'Simple techniques to calm your mind.', 'Mental Wellbeing', 'tutorial',
'Try box breathing:\n1. Breathe in slowly for 4 seconds.\n2. Hold for 4 seconds.\n3. Breathe out slowly for 4 seconds.\n4. Hold for 4 seconds.\n5. Repeat 5 times.\n\nOther helpful habits:\n- Sleep 7 to 9 hours.\n- Take a short walk each day.\n- Talk to a friend or family member you trust.\n\nIf stress feels overwhelming, speak to a health worker or someone you trust as soon as possible.'),
('Understanding Blood Pressure', 'What the numbers mean and how to keep them healthy.', 'Chronic Conditions', 'guide',
'Blood pressure is written as two numbers, for example 120/80 mmHg. The first is the pressure when your heart beats and the second is the pressure between beats.\n\nA reading of around 120/80 is generally considered healthy. If your readings are regularly 140/90 or higher, see a health worker for advice.\n\nWays to help keep it healthy:\n- Eat less salt and fewer processed foods.\n- Stay physically active.\n- Keep a healthy weight and avoid tobacco.\n- Take any prescribed medicine exactly as advised.\n- Have your blood pressure checked regularly.'),
('First Aid: Cuts, Burns and Nosebleeds', 'What to do while waiting for medical help.', 'First Aid', 'checklist',
'Cuts:\n- Wash your hands, then press firmly on the wound with a clean cloth.\n- Rinse with clean water and cover with a clean dressing.\n\nBurns:\n- Cool the burn under cool running water for about 20 minutes.\n- Do not apply butter, toothpaste or oil.\n- Cover loosely with a clean non-fluffy cloth.\n- Get medical help for large burns or burns on the face, hands or joints.\n\nNosebleeds:\n- Sit up and lean forward.\n- Pinch the soft part of the nose for 10 minutes.\n- Seek help if bleeding does not stop.'),
('Antenatal Care: What to Expect', 'Why regular visits protect you and your baby.', 'Maternal & Child Health', 'guide',
'Start antenatal visits as early as possible and attend all the visits your health worker recommends.\n\nDuring pregnancy:\n- Take iron and folic acid supplements as advised.\n- Sleep under a treated mosquito net.\n- Eat a balanced diet and drink clean water.\n\nGo to the hospital immediately if you have heavy bleeding, a severe headache, blurred vision, swelling of the face or hands, fever, or if your baby is moving less than usual.'),
('Staying Active: A 30-Minute Plan', 'Build a simple exercise habit.', 'Fitness', 'tutorial',
'Adults benefit from about 150 minutes of moderate activity each week.\n\nStart simple:\n- Walk briskly for 30 minutes, 5 days a week.\n- Start slowly and build up gradually.\n- Warm up and stretch before and after.\n- Drink water before, during and after.\n\nStop and seek help if you feel chest pain, severe dizziness or difficulty breathing.'),
('Child Immunisation Checklist', 'Keep your child protected on schedule.', 'Maternal & Child Health', 'checklist',
'Checklist:\n- Keep your child health card safe and bring it to every visit.\n- Follow the vaccine schedule written on the card.\n- If a dose is missed, visit the clinic and ask how to catch up. Do not skip it.\n- Mild fever or a sore arm or leg after a vaccine is common and usually passes quickly.\n- Ask the nurse if you have any questions or worries.');

INSERT INTO forum_topics (user_id, title, body, category, created_at) VALUES
((SELECT id FROM users WHERE email='ibrahim.conteh@example.com'), 'How do you keep mosquitoes away at night?', 'Rainy season is here. Besides sleeping under a treated net, what else do you do at home to keep mosquitoes away? I would love to hear practical tips from other members.', 'Malaria & Infectious Diseases', DATE_SUB(NOW(), INTERVAL 1 DAY)),
((SELECT id FROM users WHERE email='aminata.kamara@example.com'), 'Tips for eating healthy on a small budget', 'Fresh food can get expensive. Which affordable local foods do you rely on to keep meals nutritious for the whole family?', 'Nutrition', DATE_SUB(NOW(), INTERVAL 2 DAY)),
((SELECT id FROM users WHERE email='mohamed.sesay@example.com'), 'Managing high blood pressure: share your daily routine', 'If you are living with high blood pressure, what small daily habits help you? Remember to follow the advice of your own health worker and take any prescribed medicine as directed.', 'Chronic Conditions', DATE_SUB(NOW(), INTERVAL 3 DAY)),
((SELECT id FROM users WHERE email='fatmata.bangura@example.com'), 'Dealing with stress before exams and work deadlines', 'How do you stay calm when everything feels urgent? I find that a short walk and a few slow breaths help. What works for you?', 'Mental Wellbeing', DATE_SUB(NOW(), INTERVAL 4 DAY)),
((SELECT id FROM users WHERE email='mohamed.sesay@example.com'), 'Best home exercises for complete beginners', 'You do not need a gym to get started. Brisk walking, squats and stretching are a good beginning. What was your first routine?', 'Fitness', DATE_SUB(NOW(), INTERVAL 5 DAY)),
((SELECT id FROM users WHERE email='ibrahim.conteh@example.com'), 'What should be in a home first aid kit?', 'Let us build a checklist together. I would start with clean dressings, bandages, antiseptic wipes, gloves and a thermometer. What else would you add?', 'First Aid', DATE_SUB(NOW(), INTERVAL 6 DAY)),
((SELECT id FROM users WHERE email='aminata.kamara@example.com'), 'Questions about the childhood vaccine schedule', 'Ask your questions here about when children should receive their vaccines and what to do if a dose is missed. Keep your child''s health card safe and bring it to every visit.', 'Maternal & Child Health', DATE_SUB(NOW(), INTERVAL 7 DAY));

INSERT INTO forum_replies (topic_id, user_id, body, created_at) VALUES
((SELECT id FROM forum_topics WHERE title='How do you keep mosquitoes away at night?' LIMIT 1), (SELECT id FROM users WHERE email='fatmata.bangura@example.com'), 'Clearing standing water around the compound made the biggest difference for us, and we keep doors and windows closed after sunset.', DATE_SUB(NOW(), INTERVAL 20 HOUR)),
((SELECT id FROM forum_topics WHERE title='How do you keep mosquitoes away at night?' LIMIT 1), (SELECT id FROM users WHERE email='aminata.kamara@example.com'), 'Please also check your net for holes regularly and tuck it in under the mattress.', DATE_SUB(NOW(), INTERVAL 10 HOUR)),
((SELECT id FROM forum_topics WHERE title='Tips for eating healthy on a small budget' LIMIT 1), (SELECT id FROM users WHERE email='ibrahim.conteh@example.com'), 'Beans, groundnuts and eggs are affordable sources of protein. We also buy vegetables that are in season because they are cheaper.', DATE_SUB(NOW(), INTERVAL 30 HOUR)),
((SELECT id FROM forum_topics WHERE title='Managing high blood pressure: share your daily routine' LIMIT 1), (SELECT id FROM users WHERE email='fatmata.bangura@example.com'), 'A 30-minute walk after dinner and using less salty seasoning helped me. Keep your regular check-ups too.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
((SELECT id FROM forum_topics WHERE title='Dealing with stress before exams and work deadlines' LIMIT 1), (SELECT id FROM users WHERE email='aminata.kamara@example.com'), 'Box breathing helps me: in for four, hold for four, out for four, hold for four. Try five rounds.', DATE_SUB(NOW(), INTERVAL 3 DAY)),
((SELECT id FROM forum_topics WHERE title='What should be in a home first aid kit?' LIMIT 1), (SELECT id FROM users WHERE email='aminata.kamara@example.com'), 'Add a list of emergency numbers and keep the kit somewhere everyone in the house can find it.', DATE_SUB(NOW(), INTERVAL 5 DAY));
