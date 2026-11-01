# Yetkazib berish shartnomasi: jarima va buxgalteriya

Laravel 12, Blade, SQLite. Dastur shartnomani yuritadi, kechikish jarimasini hisoblaydi va
buxgalteriyani (to'lov bosqichlari, smeta, reestr, bekor qilish) boshqaradi.

Noaniq joylar bo'yicha qarorlar va qilinmagan ishlar: [ASSUMPTIONS.md](ASSUMPTIONS.md).

## Ishga tushirish (Docker)

Kerak: Docker va Docker Compose. Hamma narsa konteynerda: PHP o'rnatish shart emas.

```bash
git clone <repo-url> && cd <repo>
docker compose up --build -d
```

Brauzerda: http://127.0.0.1:8000. Birinchi ishga tushishda baza yaratiladi va demo ma'lumot yoziladi.
Port band bo'lsa: `APP_PORT=8124 docker compose up --build -d`.

Testlar (demo bazaga tegmaydi):

```bash
docker compose run --rm test
```

Bazani tozalab qaytadan boshlash:

```bash
docker compose down -v
```

Konteyner `www-data` foydalanuvchisi bilan, Linux capability'larsiz ishlaydi. Port faqat
`127.0.0.1` da ochiq. SQLite fayli `app_data` volume'ida saqlanadi.

## Docker'siz

PHP 8.4+ (`bcmath`, `pdo_sqlite`) va Composer kerak.

```bash
composer run setup
php artisan serve
php artisan test
```

## Demo foydalanuvchilar

Parol hammasida: `password`

| Rol | Email | Imkoniyat |
|---|---|---|
| Ijrochi | `ijrochi@demo.uz` | Shartnoma yaratadi, faollashtiradi, partiya kiritadi. Faqat o'z shartnomalari |
| Boshliq | `boshliq@demo.uz` | Hamma shartnomani ko'radi, jarimani tasdiqlaydi yoki rad etadi |
| Buxgalter | `buxgalter@demo.uz` | Smeta va to'lovlarni yuritadi, to'lovni bekor qiladi |

## Demo ma'lumot

Smeta: Dori vositalari, 2026: mart 3000,00, aprel 2000,00.

| Shartnoma | Holat | Nima uchun |
|---|---|---|
| `SH-2026/001` | Qoralama | A misoli |
| `SH-2026/002` | Faol, hech narsa kelmagan | 1-misol: hisoblash sanasi `2026-03-15` → 37,50 |
| `SH-2026/003` | Faol, 14-martda 600 dona | 2-misol: hisoblash sanasi `2026-03-16` → 35,00 |

Hisoblash sanasi standart holatda bugun, shuning uchun demo shartnomalarda jarima limitga yetgan
(1250,00) ko'rinadi. Misollarni ko'rish uchun sanani qo'lda o'zgartiring.

## A–D misollarini brauzerda tekshirish

To'lovlarda sana sifatida 2026-yil sanasini tanlang, aks holda 5-tekshiruv rad etadi.

- **A.** Buxgalter: `SH-2026/001` ga 750 avans → "Shartnoma faol emas". Ijrochi faollashtiradi.
  Buxgalter yana kiritadi → o'tadi, Smeta sahifasida mart qoldig'i 2250,00.
- **B.** Ijrochi: partiya 1000 dona. Unga qadar Yetkazib berish bosqichi rad etiladi. Buxgalter
  1000 to'laydi → Qisman to'langan, keyin 800 → rad, keyin 750 → To'langan.
- **C.** Buxgalter 1000 lik to'lovni sabab bilan bekor qiladi: yozuv "Bekor qilingan to'lovlar"
  jadvaliga o'tadi, qoldiqlar qaytadi. Keyin 900 kiritadi.
- **D.** `SH-2026/002`, hisoblash sanasi `2026-03-15`: jarima 37,50, buxgalteriyada 0,00. Boshliq
  tasdiqlaydi → 37,50. Summa maydoniga 30,00 yozib tasdiqlaydi → 30,00.

## Kod tuzilishi

Hisob qoidalari `app/Domain` da, sahifa kodidan alohida:

| Klass | Vazifasi |
|---|---|
| `Domain/Money` | Pul: butun tiyin, bcmath, yaxlitlash |
| `Domain/Penalty/PenaltyCalculator` | Jarima formulasi. Baza va HTTP'ga bog'liq emas |
| `Domain/Penalty/PenaltyLedger`, `PenaltyService` | Tasdiqlangan jarima qoidasi, boshliq qarori |
| `Domain/Accounting/PaymentSchedule` | Bosqich summasi, qoldig'i va holati (reestrdan) |
| `Domain/Accounting/BudgetLedger` | Smeta qoldig'i (reestrdan) |
| `Domain/Accounting/PaymentGuard` | 5 ta tekshiruv, aniq tartibda |
| `Domain/Accounting/PaymentService` | To'lov va bekor qilish, qulf ichida |
| `Domain/RowLock` | SQLite'da ham ishlaydigan qator qulfi |

Qoldiq va holatlar bazada saqlanmaydi, har safar reestrdan hisoblanadi. Reestr yozuvini o'chirish va
o'zgartirishni model ham, SQLite trigger ham taqiqlaydi.

## Testlar

`tests/Unit`: pul va jarima formulasi. `tests/Feature`: A–D misollari, tekshiruvlar tartibi,
bosqich holatlari, bekor qilish, ruxsatlar, smeta, seeder.

`tests/Feature/ConcurrencyTest` haqiqiy poyga qiladi: har bir buxgalter alohida PHP jarayon, baza
umumiy SQLite fayli. Oxirgi smeta qoldig'i, bitta bosqich va bitta to'lovni bekor qilish uchun faqat
bittasi o'tishi tekshiriladi.
