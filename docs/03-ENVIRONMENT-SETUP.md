# Part 3: 로컬 WordPress 설치 및 개발 환경 구축

## 3.1 환경 구축 개요

본 프로젝트는 `/Users/hansync/Dropbox/Project2025-dev/wordpress` 디렉토리에 WordPress를 직접 설치하고, 해당 WordPress 인스턴스 내에서 플러그인을 개발하는 방식으로 진행합니다.

```
/Users/hansync/Dropbox/Project2025-dev/wordpress/
├── docs/                           # 프로젝트 문서
├── wp-admin/                       # WordPress 관리자 (설치 후 생성)
├── wp-content/
│   ├── plugins/
│   │   └── wp-ai-rewriter/        # 개발할 플러그인
│   ├── themes/
│   └── uploads/
├── wp-includes/                    # WordPress 코어 (설치 후 생성)
├── wp-config.php                   # WordPress 설정 (설치 후 생성)
└── index.php                       # WordPress 진입점 (설치 후 생성)
```

## 3.2 사전 요구사항

### 3.2.1 시스템 요구사항
- macOS (현재 환경)
- PHP 8.0 이상
- MySQL 5.7 이상 또는 MariaDB 10.3 이상
- Nginx 또는 Apache (로컬 웹서버)

### 3.2.2 Homebrew를 통한 필수 패키지 설치

```bash
# Homebrew 업데이트
brew update

# PHP 설치 (8.2 권장)
brew install php@8.2

# MySQL 설치
brew install mysql

# Nginx 설치 (선택: Apache 대신)
brew install nginx

# WP-CLI 설치
brew install wp-cli

# Composer 설치 (의존성 관리용)
brew install composer
```

### 3.2.3 서비스 시작

```bash
# MySQL 시작
brew services start mysql

# MySQL 보안 설정 (최초 1회)
mysql_secure_installation

# Nginx 시작
brew services start nginx

# PHP-FPM 시작
brew services start php@8.2
```

## 3.3 MySQL 데이터베이스 설정

### 3.3.1 데이터베이스 생성

```bash
# MySQL 접속
mysql -u root -p

# 데이터베이스 및 사용자 생성
CREATE DATABASE wp_ai_rewriter CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'wp_dev'@'localhost' IDENTIFIED BY 'dev_password_2025';
GRANT ALL PRIVILEGES ON wp_ai_rewriter.* TO 'wp_dev'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3.3.2 데이터베이스 정보 기록

```
Database Name: wp_ai_rewriter
Username: wp_dev
Password: dev_password_2025
Host: localhost
Table Prefix: wp_
```

## 3.4 Nginx 웹서버 설정

### 3.4.1 Nginx 설정 파일 생성

```nginx
# /opt/homebrew/etc/nginx/servers/wordpress-dev.conf

server {
    listen 8080;
    server_name localhost;

    root /Users/hansync/Dropbox/Project2025-dev/wordpress;
    index index.php index.html;

    # 로그 파일
    access_log /opt/homebrew/var/log/nginx/wordpress-access.log;
    error_log /opt/homebrew/var/log/nginx/wordpress-error.log;

    # 최대 업로드 크기
    client_max_body_size 64M;

    # WordPress Permalink 지원
    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    # PHP 처리
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    # 정적 파일 캐싱
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires max;
        log_not_found off;
    }

    # .htaccess 및 숨김 파일 접근 차단
    location ~ /\. {
        deny all;
    }

    # wp-config.php 직접 접근 차단
    location = /wp-config.php {
        deny all;
    }
}
```

### 3.4.2 Nginx 설정 적용

```bash
# 설정 문법 검사
nginx -t

# Nginx 재시작
brew services restart nginx
```

## 3.5 WordPress 설치

### 3.5.1 WP-CLI를 사용한 설치

```bash
# 프로젝트 디렉토리로 이동
cd /Users/hansync/Dropbox/Project2025-dev/wordpress

# WordPress 코어 다운로드 (docs 폴더 유지하면서)
wp core download --locale=ko_KR --skip-content

# wp-config.php 생성
wp config create \
    --dbname=wp_ai_rewriter \
    --dbuser=wp_dev \
    --dbpass=dev_password_2025 \
    --dbhost=localhost \
    --dbprefix=wp_ \
    --locale=ko_KR

# 개발용 디버그 설정 추가
wp config set WP_DEBUG true --raw
wp config set WP_DEBUG_LOG true --raw
wp config set WP_DEBUG_DISPLAY false --raw
wp config set SCRIPT_DEBUG true --raw
wp config set SAVEQUERIES true --raw

# WordPress 설치
wp core install \
    --url=http://localhost:8080 \
    --title="AI Content Rewriter Dev" \
    --admin_user=admin \
    --admin_password=admin123! \
    --admin_email=dev@localhost.com \
    --skip-email

# 퍼머링크 구조 설정
wp rewrite structure '/%postname%/' --hard
wp rewrite flush --hard
```

### 3.5.2 기본 플러그인 설치 (개발용)

```bash
# Query Monitor (디버깅 필수)
wp plugin install query-monitor --activate

# Debug Bar
wp plugin install debug-bar --activate

# WP Crontrol (크론 작업 관리)
wp plugin install wp-crontrol --activate

# 기본 플러그인 비활성화
wp plugin deactivate hello akismet
```

## 3.6 플러그인 개발 디렉토리 구조

### 3.6.1 플러그인 기본 구조 생성

```bash
# 플러그인 디렉토리 생성
mkdir -p wp-content/plugins/wp-ai-rewriter

# 기본 파일 구조 생성
cd wp-content/plugins/wp-ai-rewriter

mkdir -p {includes,admin,src,assets,languages,tests}
mkdir -p admin/{css,js,partials}
mkdir -p src/{Content,AI,Prompt,Post,Scheduler}
mkdir -p assets/{images,icons}
```

### 3.6.2 최종 플러그인 디렉토리 구조

```
wp-content/plugins/wp-ai-rewriter/
├── wp-ai-rewriter.php              # 메인 플러그인 파일
├── uninstall.php                   # 언인스톨 훅
├── readme.txt                      # WordPress 플러그인 설명
├── composer.json                   # PHP 의존성
│
├── includes/                       # 핵심 클래스
│   ├── class-plugin-core.php
│   ├── class-activator.php
│   ├── class-deactivator.php
│   └── class-loader.php
│
├── admin/                          # 관리자 영역
│   ├── class-admin.php
│   ├── class-settings-page.php
│   ├── class-rewriter-page.php
│   ├── class-scheduler-page.php
│   ├── css/
│   ├── js/
│   └── partials/
│
├── src/                            # 비즈니스 로직
│   ├── Content/
│   ├── AI/
│   ├── Prompt/
│   ├── Post/
│   └── Scheduler/
│
├── languages/                      # 다국어
├── assets/                         # 정적 자원
└── tests/                          # 테스트
```

## 3.7 개발 도구 및 워크플로우

### 3.7.1 VS Code 설정

```json
// .vscode/settings.json (프로젝트 루트에 생성)
{
    "php.validate.executablePath": "/opt/homebrew/bin/php",
    "intelephense.environment.phpVersion": "8.2.0",
    "intelephense.stubs": ["wordpress"],
    "files.exclude": {
        "wp-admin": true,
        "wp-includes": true
    },
    "search.exclude": {
        "wp-admin/**": true,
        "wp-includes/**": true
    }
}
```

### 3.7.2 일일 개발 워크플로우

```bash
# 1. 서비스 상태 확인
brew services list

# 2. 서비스 시작 (필요시)
brew services start mysql
brew services start nginx
brew services start php@8.2

# 3. 브라우저에서 확인
# WordPress: http://localhost:8080
# 관리자: http://localhost:8080/wp-admin

# 4. 플러그인 개발
cd /Users/hansync/Dropbox/Project2025-dev/wordpress/wp-content/plugins/wp-ai-rewriter
# 코드 편집...

# 5. 플러그인 활성화/비활성화
wp plugin activate wp-ai-rewriter
wp plugin deactivate wp-ai-rewriter

# 6. 디버그 로그 확인
tail -f /Users/hansync/Dropbox/Project2025-dev/wordpress/wp-content/debug.log
```

### 3.7.3 유용한 WP-CLI 명령어

```bash
# 플러그인 scaffold (기본 구조 생성)
wp scaffold plugin wp-ai-rewriter --plugin_name="AI Content Rewriter" --activate

# 현재 플러그인 목록
wp plugin list

# 캐시 초기화
wp cache flush
wp transient delete --all

# 데이터베이스 백업
wp db export backup.sql

# 게시글 테스트 생성
wp post create --post_title="테스트 게시글" --post_content="테스트 내용" --post_status=draft
```

## 3.8 개발 환경 시작/종료 스크립트

### 3.8.1 시작 스크립트

```bash
#!/bin/bash
# scripts/start-dev.sh

echo "=== WordPress 개발 환경 시작 ==="

# 서비스 시작
brew services start mysql
brew services start nginx
brew services start php@8.2

# 상태 확인
sleep 2
brew services list | grep -E "(mysql|nginx|php)"

echo ""
echo "WordPress: http://localhost:8080"
echo "관리자: http://localhost:8080/wp-admin"
echo "ID: admin / PW: admin123!"
echo ""
echo "=== 환경 준비 완료 ==="
```

### 3.8.2 종료 스크립트

```bash
#!/bin/bash
# scripts/stop-dev.sh

echo "=== WordPress 개발 환경 종료 ==="

brew services stop php@8.2
brew services stop nginx
brew services stop mysql

echo "=== 모든 서비스 종료됨 ==="
```

## 3.9 문제 해결

### 3.9.1 일반적인 문제

| 문제 | 원인 | 해결책 |
|------|------|--------|
| 502 Bad Gateway | PHP-FPM 미실행 | `brew services restart php@8.2` |
| DB 연결 실패 | MySQL 미실행 | `brew services restart mysql` |
| 404 에러 | Nginx 설정 오류 | 설정 파일 확인 후 `nginx -t` |
| 권한 문제 | 파일 소유권 | `chmod -R 755 wp-content` |
| 느린 로딩 | PHP opcache | `php.ini`에서 opcache 활성화 |

### 3.9.2 로그 파일 위치

```bash
# WordPress 디버그 로그
/Users/hansync/Dropbox/Project2025-dev/wordpress/wp-content/debug.log

# Nginx 로그
/opt/homebrew/var/log/nginx/wordpress-error.log
/opt/homebrew/var/log/nginx/wordpress-access.log

# PHP 에러 로그
/opt/homebrew/var/log/php-fpm.log

# MySQL 로그
/opt/homebrew/var/mysql/*.err
```

## 3.10 환경 설치 체크리스트

- [ ] Homebrew 설치 및 업데이트
- [ ] PHP 8.2 설치 및 시작
- [ ] MySQL 설치, 시작, 보안 설정
- [ ] Nginx 설치 및 설정
- [ ] WordPress 데이터베이스 생성
- [ ] WordPress 코어 다운로드
- [ ] wp-config.php 생성 및 설정
- [ ] WordPress 설치 완료
- [ ] 개발용 플러그인 설치 (Query Monitor 등)
- [ ] 플러그인 개발 디렉토리 생성
- [ ] 브라우저에서 접속 확인

---
*문서 버전: 1.1*
*작성일: 2025-12-28*
*이전 문서: [02-TECHNICAL-ARCHITECTURE.md](./02-TECHNICAL-ARCHITECTURE.md)*
*다음 문서: [04-PLUGIN-SPECIFICATIONS.md](./04-PLUGIN-SPECIFICATIONS.md)*
