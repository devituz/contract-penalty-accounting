# ASSUMPTIONS

Task'da aytilmagan joylar bo'yicha qabul qilingan qarorlar. Har bir qarorda sabab yozilgan.
Testda tekshirilganlari `(test)` deb belgilangan.

## Task o'zi so'ragan noaniqliklar

### Jarimaning yakuniy to'lovga ta'siri
**Jarima to'lovdan ushlab qolinmaydi.** U yetkazuvchiga qo'yiladigan alohida talab: buxgalteriya
sahifasida ko'rinadi, lekin bosqich summasi, bosqich qoldig'i, reestr va smeta o'zgarmaydi.

Sabab: agar jarima oxirgi bosqichdan ayrilsa, B misolidagi 1750 summasi jarimaga qarab o'zgarib
qolardi. Bundan tashqari, jarima bosqichlar to'liq to'langandan keyin ham tasdiqlanishi mumkin:
unda ayirishga narsa qolmaydi. Alohida talab ikkala holatda ham bir xil ishlaydi. Smetaga ham
kirim sifatida qaytmaydi, chunki undirilgan jarima oy xarajatini oshirib bermaydi.

Dasturda jarimaning undirilishi yuritilmaydi (qarang: "Qilinmagan ishlar").

### Jarima to'lovlar kiritilgandan keyin tasdiqlansa
Yuqoridagi qarordan kelib chiqadi: to'lovlar va qoldiqlar qayta hisoblanmaydi, jarima faqat
alohida ko'rinadi. (test)

### Bekor qilingan to'lovning smeta oyi allaqachon o'tgan bo'lsa
**Bekor qilish taqiqlanmaydi, summa aynan o'sha oy qatoriga qaytadi.** Qoldiq reestrdan
hisoblanadi, shuning uchun boshqa oyga "ko'chirish" bo'lmaydi: bu smetani yashirincha o'zgartirish
bo'lardi. Oyni yopish tushunchasi yo'q, chunki Task'da to'lov sanasi faqat moliyaviy yilga mos
bo'lishi kerak: martdagi xato iyunda tuzatilsa, mart qatoridan qayta to'lash mumkin. (test)

Agar moliyaviy yil tugagan bo'lsa, summa o'tgan yil qatoriga qaytadi, lekin undan yangi to'lov
qilib bo'lmaydi: yangi sana yilga mos kelmaydi. Bu to'g'ri xulq deb hisoblandi.

### Hisoblash sanasi muddatdan oldin bo'lsa
Jarima 0,00, kechikish 0 kun, jadval bo'sh. Muddat kunining o'zi kechikish emas. Karta baribir
muddat sanasi va limitni ko'rsatadi. (test)

## Jarima

- **Qoralama shartnomada jarima yo'q**, muddat o'tgan bo'lsa ham: shartnoma kuchga kirmagan. (test)
- **Kech faollashtirilgan shartnoma.** Jarima baribir `boshlanish + muddat` dan hisoblanadi: Task
  qoidasi faollashtirish sanasiga bog'lanmagan.
- **Limit ustidagi kunlar.** Kechikish kunlari sanalishda davom etadi, lekin jamiga qo'shilmaydi.
  Jadvalda jamiga haqiqatda qo'shilgan summa turadi, shuning uchun ustun yig'indisi har doim
  kartadagi jarimaga teng. Limitga yetgan kun qisman kesiladi. (test)
- **Limit asosi** muddat kuni oxiridagi qoldiq. Muddat kuni kelgan partiya uni kamaytiradi,
  keyingilari kamaytirmaydi. (test)
- **Boshliq summani faqat kamaytiradi.** 0 dan katta va hisoblangandan oshmasligi kerak.
  Voz kechish uchun "Rad etish" bor. Task'da faqat kamaytirish misoli berilgan. (test)
- **Hisoblangan summa har doim serverda qayta hisoblanadi**, sahifadan olinmaydi. (test)
- **Kelajak sana uchun qaror qabul qilinmaydi.** Ko'rib chiqish mumkin (prognoz), tasdiqlash
  mumkin emas: hali o'tmagan kunlar uchun jarima yo'q. (test)
- **Qarorlar jurnali: oxirgi qaror amal qiladi.** Jarima kundan-kunga o'sadi, shuning uchun bitta
  "tasdiqlangan" yozuv yetmaydi. 15-martda tasdiqlangan bo'lsa, 20-martda boshliq yangi sana bilan
  qayta tasdiqlaydi. Tarix o'chmaydi. Oxirgi qaror rad etish bo'lsa, buxgalteriyada 0,00. (test)
- **Tasdiqlash qotiriladi.** Keyin orqa sana bilan partiya kiritilib hisob o'zgarsa ham tasdiqlangan
  summa o'zgarmaydi, lekin sahifada ogohlantirish chiqadi. Pul haqidagi qarorni tizim o'zi
  o'zgartirmasligi kerak. (test)
- **Hisoblash sanasi** 2000–2099 yillar bilan cheklangan, jadval cheksiz o'smasligi uchun.

## To'lov bosqichlari

- **Holat ustuvorligi:** Qoralama → Rejalashtirilgan; qoldiq yo'q → To'langan; muddat o'tgan →
  Muddati o'tgan; qisman → Qisman to'langan; shart bajarilgan → To'lashga tayyor; aks holda
  Rejalashtirilgan. Muddat kunining o'zi hali o'tmagan. (test)
- **Muddati o'tgan shart bajarilmagan bosqichga ham qo'yiladi.** Task ta'rifi faqat ikki shartni
  aytadi: muddat o'tgan va qoldiq bor. Bunday bosqichni to'lab bo'lmaydi: to'lov tekshiruvi shartni
  alohida tekshiradi.
- **Qoralamada Muddati o'tgan bo'lmaydi**: shartnoma kuchga kirmagan. (test)
- **To'lashga tayyor oldingi bosqichga qaramaydi.** Task'da Rejalashtirilgan aynan "to'lov sharti
  bajarilmagan" deb ta'riflangan. Oldingi bosqich to'lov paytida 3-tekshiruvda ushlanadi.
- **Bosqich summasi saqlanmaydi**: `foiz × pullik summa`, faollashgach qatorlar o'zgarmagani uchun
  barqaror. Foiz yaxlitlanadi, shuning uchun oxirgi bosqich qolgan summani oladi va yig'indi
  tiyinigacha to'g'ri chiqadi (33,33 / 33,33 / 33,34). (test)
- **Faollashtirishda** foizlar jami 100%, pullik summa noldan katta bo'lishi kerak. (test)
- **Bosqichlarni ijrochi** qoralamada kiritadi (standart 30/70). Faollashgach o'zgarmaydi.

## To'lov

- **Tekshiruvlar tartibi** Task'dagidek va qulf ichida bajariladi. Faqat yozilish shakli
  (`12,345`, bo'sh maydon) forma darajasida oldin tekshiriladi. "Noldan katta" esa biznes qoidasi,
  u o'z navbatida (4-tekshiruvda) ishlaydi. Manfiy summa shakl xatosi hisoblanadi. (test)
- **Oy mos kelishi shart emas.** Task faqat moliyaviy yilni talab qiladi, shuning uchun martdagi
  to'lovni aprel qatoridan qilish mumkin. (test)
- **Moliyaviy yil = kalendar yil.**
- **Kelajak sanali to'lovga yo'l qo'yiladi.** Task'dagi tekshiruvlar ro'yxati to'liq deb olindi,
  o'zimdan yangi qoida qo'shmadim. Shu sababli to'lov sanasi shartnoma boshlanishidan oldin bo'lishi
  ham tekshirilmaydi.
- **Shartnoma xarajat moddasiga bog'lanmagan**, chunki Task'dagi shartnoma maydonlarida u yo'q.
  Qaysi moddadan to'lashni buxgalter tanlaydi.
- **Oldingi bosqich to'lovini bekor qilish taqiqlanmaydi**, keyingi bosqichda faol to'lov bo'lsa ham.
  Bekor qilish xatoni tuzatish vositasi. Taqiqlansa, buxgalter sog'lom to'lovlarni ham bekor qilib,
  reestrni ifloslantirishga majbur bo'lardi. Sahifada ogohlantirish chiqadi, keyingi bosqichga yangi
  to'lov esa bloklanadi. (test)
- **Yakunlangan shartnoma to'lov qabul qiladi** (Task'da aytilgan). Holat to'lovga bog'liq emas:
  yetkazib berish yakunlangani pul yakunlangani degani emas. (test)

## Smeta

- **Ajratilgan summa to'langandan kam qilinmaydi**, aks holda qoldiq manfiy bo'lardi. (test)
- **Smeta qatori o'chirilmaydi**: reestr unga tayanadi. Summani o'zgartirish mumkin.
- Bir yil, modda va oy juftligi takrorlanmaydi. (test)

## Yetkazib berish

- **Pullik va bonus alohida kiritiladi.** Bonus pullik kamomadni yopmaydi. (test)
- **Bonus shartnomani yakunlashga ta'sir qilmaydi.** Yakunlangandan keyin qolgan bonus kiritilishi
  mumkin, pullik kiritib bo'lmaydi: qoldiq 0. (test)
- **Partiya sanasi** shartnoma boshlanishidan oldin va kelajakda bo'lishi mumkin emas. Orqa sana
  bilan, tartibsiz kiritish mumkin: hisob sanalar bo'yicha yuradi. (test)
- **Miqdor butun son** ("dona"), narx 2 xonali.
- **Qator nomi takrorlanishi**, narxi 0 bo'lgan faqat-bonus qator bo'lishi mumkin. Pullik tovar
  narxi noldan katta bo'lishi kerak.

## Ruxsatlar

- Task jadvalidan tashqari: **boshliq** buxgalteriya va smetani faqat ko'radi ("hamma shartnomani
  ko'radi"), **buxgalter** shartnoma sahifasini faqat ko'radi (to'lash uchun kerak), **ijrochi** o'z
  shartnomasida bosqich holatini ko'radi, lekin buxgalteriya sahifasiga kira olmaydi. (test)
- Boshqa ijrochining shartnomasi 403 qaytaradi. (test)
- Boshliq shartnoma yaratmaydi: Task'da bu ijrochining vazifasi. (test)

## Pul va bir vaqtda ishlash

- **Pul butun tiyinda saqlanadi** (`bigInteger`). SQLite'da `decimal` ustun aslida `REAL`, ya'ni
  float. Foiz va ko'paytirish bcmath orqali. Summa 13 xonagacha.
- **Qulf.** SQLite'da `lockForUpdate()` hech narsa qilmaydi. Shuning uchun tranzaksiya birinchi
  amali sifatida qatorga yozamiz (`App\Domain\RowLock`): ikkinchi yozuvchi `busy_timeout` davomida
  kutadi va yangilangan ma'lumotni ko'radi. Tartib: avval shartnoma, keyin smeta qatori.
  Shartnoma qulflanadi, chunki 3-tekshiruv bir nechta bosqichga qaraydi.
- **Birinchi amal qulf bo'lishi shart.** Konkurent test shu xatoni tutgan: `void()` ichida oldin
  o'qish bo'lgani uchun yutqazgan jarayon aniq xabar o'rniga `database is locked` olardi.
- **Qulf isboti.** `RowLock` vaqtincha o'chirilganda uchala konkurent test yiqiladi.
- **Jarima formulasi** alohida yozilgan Python/Decimal hisobi bilan 5000 ta tasodifiy holatda
  solishtirilgan: farq yo'q. Bu skript repoda yo'q, bir martalik tekshiruv edi.

## Qilinmagan ishlar

- Partiyani tahrirlash yoki bekor qilish yo'q. Xato partiya kiritilsa, tuzatib bo'lmaydi. Bu
  jarima va yakunlanishga ta'sir qiladi, shuning uchun alohida qaror kerak.
- Jarimaning yetkazuvchidan undirilishi yuritilmaydi, faqat summa ko'rsatiladi.
- Smeta o'zgarishlari tarixi yuritilmaydi.
- Reestrda sahifalash va filtr yo'q.
- Reestrni baza darajasida himoyalovchi triggerlar faqat SQLite uchun yozilgan. PostgreSQL'ga
  o'tilsa, ularni qayta yozish kerak (model darajasidagi himoya baribir ishlaydi).
- `php artisan serve` demo uchun. Production'ga php-fpm va nginx kerak.
