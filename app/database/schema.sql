-- Morphora Spaces - database schema (MySQL 5.7+ / MariaDB 10.3+, utf8mb4)
-- Loaded by /install. Safe to read; do not run on a live database (it creates tables only if missing).

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  pass_hash VARCHAR(255) NOT NULL,
  role VARCHAR(32) NOT NULL DEFAULT 'subscriber',   -- see ROLES in app/admin/auth.php
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(64) NOT NULL PRIMARY KEY,
  v TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fixed pages (about, services, contact, legal …) and the SEO text of template pages (home, portfolio …)
CREATE TABLE IF NOT EXISTS pages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(190) NOT NULL UNIQUE,
  template VARCHAR(30) NOT NULL DEFAULT 'page',
  nav VARCHAR(40) NOT NULL DEFAULT '',
  seo_title VARCHAR(255) NOT NULL DEFAULT '',
  seo_desc VARCHAR(400) NOT NULL DEFAULT '',
  focus_keyword VARCHAR(190) NOT NULL DEFAULT '',
  canonical_url VARCHAR(500) NOT NULL DEFAULT '',
  og_title VARCHAR(255) NOT NULL DEFAULT '',
  og_desc VARCHAR(400) NOT NULL DEFAULT '',
  og_image VARCHAR(255) NOT NULL DEFAULT '',
  schema_type VARCHAR(32) NOT NULL DEFAULT 'auto',
  foot_label VARCHAR(255) NOT NULL DEFAULT '',
  foot_title VARCHAR(255) NOT NULL DEFAULT '',
  body MEDIUMTEXT NULL,
  noindex TINYINT(1) NOT NULL DEFAULT 0,
  nofollow TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(190) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  excerpt TEXT NULL,
  category VARCHAR(100) NOT NULL DEFAULT 'Office Design',
  body MEDIUMTEXT NULL,
  cover VARCHAR(255) NOT NULL DEFAULT '',
  thumb VARCHAR(255) NOT NULL DEFAULT '',
  author VARCHAR(100) NOT NULL DEFAULT 'Morphora Spaces',
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  published_at DATE NULL,
  seo_title VARCHAR(255) NOT NULL DEFAULT '',
  seo_desc VARCHAR(400) NOT NULL DEFAULT '',
  focus_keyword VARCHAR(190) NOT NULL DEFAULT '',
  canonical_url VARCHAR(500) NOT NULL DEFAULT '',
  og_title VARCHAR(255) NOT NULL DEFAULT '',
  og_desc VARCHAR(400) NOT NULL DEFAULT '',
  og_image VARCHAR(255) NOT NULL DEFAULT '',
  schema_type VARCHAR(32) NOT NULL DEFAULT 'auto',
  noindex TINYINT(1) NOT NULL DEFAULT 0,
  nofollow TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,      -- user who wrote it (authors / contributors only see their own)
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY status_date (status, published_at),
  KEY created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(190) NOT NULL UNIQUE,
  name VARCHAR(190) NOT NULL,
  city VARCHAR(100) NOT NULL DEFAULT '',
  size VARCHAR(60) NOT NULL DEFAULT '',
  delivery VARCHAR(190) NOT NULL DEFAULT '',
  summary TEXT NULL,
  cover VARCHAR(255) NOT NULL DEFAULT '',
  gallery MEDIUMTEXT NULL,            -- JSON: [[thumb, full], …]
  featured TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('draft','published') NOT NULL DEFAULT 'published',
  seo_title VARCHAR(255) NOT NULL DEFAULT '',
  seo_desc VARCHAR(400) NOT NULL DEFAULT '',
  focus_keyword VARCHAR(190) NOT NULL DEFAULT '',
  canonical_url VARCHAR(500) NOT NULL DEFAULT '',
  og_title VARCHAR(255) NOT NULL DEFAULT '',
  og_desc VARCHAR(400) NOT NULL DEFAULT '',
  og_image VARCHAR(255) NOT NULL DEFAULT '',
  schema_type VARCHAR(32) NOT NULL DEFAULT 'auto',
  noindex TINYINT(1) NOT NULL DEFAULT 0,
  nofollow TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cities (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(190) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  top TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('draft','published') NOT NULL DEFAULT 'published',
  lead TEXT NULL,                     -- optional: replaces the standard intro line
  body MEDIUMTEXT NULL,               -- optional: extra local content shown on the page
  hero VARCHAR(255) NOT NULL DEFAULT '',
  seo_title VARCHAR(255) NOT NULL DEFAULT '',
  seo_desc VARCHAR(400) NOT NULL DEFAULT '',
  focus_keyword VARCHAR(190) NOT NULL DEFAULT '',
  canonical_url VARCHAR(500) NOT NULL DEFAULT '',
  og_title VARCHAR(255) NOT NULL DEFAULT '',
  og_desc VARCHAR(400) NOT NULL DEFAULT '',
  og_image VARCHAR(255) NOT NULL DEFAULT '',
  schema_type VARCHAR(32) NOT NULL DEFAULT 'auto',
  noindex TINYINT(1) NOT NULL DEFAULT 0,
  nofollow TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jobs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(190) NOT NULL UNIQUE,
  title VARCHAR(190) NOT NULL,
  location VARCHAR(190) NOT NULL DEFAULT 'Thane, Maharashtra',
  type VARCHAR(60) NOT NULL DEFAULT 'Full-time',
  experience VARCHAR(60) NOT NULL DEFAULT '',
  description MEDIUMTEXT NULL,
  status ENUM('draft','published','closed') NOT NULL DEFAULT 'draft',
  valid_through DATE NULL,
  seo_title VARCHAR(255) NOT NULL DEFAULT '',
  seo_desc VARCHAR(400) NOT NULL DEFAULT '',
  focus_keyword VARCHAR(190) NOT NULL DEFAULT '',
  canonical_url VARCHAR(500) NOT NULL DEFAULT '',
  og_title VARCHAR(255) NOT NULL DEFAULT '',
  og_desc VARCHAR(400) NOT NULL DEFAULT '',
  og_image VARCHAR(255) NOT NULL DEFAULT '',
  schema_type VARCHAR(32) NOT NULL DEFAULT 'auto',
  noindex TINYINT(1) NOT NULL DEFAULT 0,
  nofollow TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS applications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_id INT UNSIGNED NULL,
  job_title VARCHAR(190) NOT NULL DEFAULT '',
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL DEFAULT '',
  phone VARCHAR(40) NOT NULL DEFAULT '',
  portfolio VARCHAR(255) NOT NULL DEFAULT '',
  message TEXT NULL,
  cv_file VARCHAR(255) NOT NULL DEFAULT '',
  cv_name VARCHAR(255) NOT NULL DEFAULT '',
  status ENUM('new','shortlisted','interview','rejected','hired') NOT NULL DEFAULT 'new',
  notes TEXT NULL,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY job (job_id), KEY created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS enquiries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source VARCHAR(20) NOT NULL DEFAULT 'form',
  name VARCHAR(150) NOT NULL,
  company VARCHAR(150) NOT NULL DEFAULT '',
  phone VARCHAR(40) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL DEFAULT '',
  city VARCHAR(100) NOT NULL DEFAULT '',
  size VARCHAR(60) NOT NULL DEFAULT '',
  service VARCHAR(100) NOT NULL DEFAULT '',
  message TEXT NULL,
  page VARCHAR(255) NOT NULL DEFAULT '',
  referrer VARCHAR(255) NOT NULL DEFAULT '',
  utm VARCHAR(255) NOT NULL DEFAULT '',
  status ENUM('new','contacted','qualified','won','lost','spam') NOT NULL DEFAULT 'new',
  notes TEXT NULL,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  user_agent VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY status (status), KEY created (created_at), KEY ip_time (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- About page: team members (the one with a message is shown as the founder's message) and client testimonials
CREATE TABLE IF NOT EXISTS team (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  role VARCHAR(150) NOT NULL DEFAULT '',
  photo VARCHAR(255) NOT NULL DEFAULT '',
  bio TEXT NULL,
  linkedin VARCHAR(255) NOT NULL DEFAULT '',
  message MEDIUMTEXT NULL,            -- founder's message (optional)
  sort_order INT NOT NULL DEFAULT 10,
  status ENUM('draft','published') NOT NULL DEFAULT 'published',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS testimonials (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  role VARCHAR(150) NOT NULL DEFAULT '',
  company VARCHAR(150) NOT NULL DEFAULT '',
  quote TEXT NOT NULL,
  photo VARCHAR(255) NOT NULL DEFAULT '',
  sort_order INT NOT NULL DEFAULT 10,
  status ENUM('draft','published') NOT NULL DEFAULT 'published',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 301/302 redirects (old WordPress URLs are seeded here so rankings carry over)
CREATE TABLE IF NOT EXISTS redirects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  from_path VARCHAR(190) NOT NULL UNIQUE,
  to_path VARCHAR(255) NOT NULL,
  code SMALLINT NOT NULL DEFAULT 301,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  email VARCHAR(190) NOT NULL DEFAULT '',
  ok TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ip_time (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
