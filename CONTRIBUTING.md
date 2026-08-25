# Contributing to Soorin Inventory · مشارکت در سورین

Thanks for taking the time to help improve **Soorin Inventory**! Feedback of every
kind is welcome — and if the project is useful to you, please give it a ⭐.

از اینکه برای بهبودِ **سورین** وقت می‌گذاری ممنونیم! هر نوع فیدبکی ارزشمند است — و
اگر برایت مفید بود، لطفاً یک ⭐ بده.

---

## 🐛 Reporting bugs · گزارش باگ

Open a **[Bug report issue](https://github.com/soorintec/Soorin_Inventory/issues/new/choose)**
and include:

- App version (shown on the in-app **Update** page)
- OS + web server + PHP version
- Steps to reproduce, what you expected, and what happened
- Screenshots if they help

یک **issue از نوع Bug** باز کن و نسخهٔ برنامه، سیستم‌عامل/وب‌سرور/PHP، مراحلِ
بازتولید و اسکرین‌شات را بگذار.

## 💡 Suggesting features · پیشنهاد قابلیت

Open a **Feature request issue** describing what you want and *why* it helps.
Real-world workflows are the best guide for what to build next.

یک **issue از نوع Feature** باز کن و بگو چه می‌خواهی و چرا مفید است.

## 💬 Questions & discussion · پرسش و گفتگو

- General questions and ideas → **[Discussions](https://github.com/soorintec/Soorin_Inventory/discussions)**
- Licensing / quick help → **Telegram: [@Soorin_Support](https://t.me/Soorin_Support)**

## 🌍 Translations · ترجمه

Adding or improving a language takes only a few minutes — see
**[docs/TRANSLATIONS.md](docs/TRANSLATIONS.md)**. Every locale must have the same
keys as Persian (a test enforces this).

افزودن یا بهبودِ یک زبان چند دقیقه بیشتر طول نمی‌کشد — راهنما در
`docs/TRANSLATIONS.md`. همهٔ زبان‌ها باید کلیدهای یکسان با فارسی داشته باشند.

## 🧑‍💻 Development setup · راه‌اندازی توسعه

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8001
```

Panel: <http://127.0.0.1:8001/admin> — demo login `admin@yoursite.com` / `password`.

## ✅ Pull requests · درخواستِ ادغام

1. Keep each PR focused on one change.
2. Match the existing code style (the codebase uses clear names and Persian inline
   comments — please keep that consistent).
3. **Run the test suite before submitting:**
   ```bash
   php artisan test
   ```
4. Add or update tests for any behavior you change.
5. Describe *what* changed and *why* in the PR description.

هر PR را روی یک تغییر متمرکز نگه دار، سبکِ کدِ موجود را رعایت کن، قبل از ارسال
`php artisan test` را اجرا کن و برای هر تغییرِ رفتاری تست بگذار.

## 📄 License · مجوز

By contributing, you agree that your contributions are licensed under the same
terms as this project — see **[LICENSE.md](LICENSE.md)**.

با مشارکت، می‌پذیری که سهمت تحت همان شرایطِ [LICENSE.md](LICENSE.md) منتشر شود.
