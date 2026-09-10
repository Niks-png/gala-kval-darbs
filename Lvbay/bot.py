import asyncio
import logging
import re
from aiogram import Bot, Dispatcher, F
from aiogram.types import Message, CallbackQuery, InlineKeyboardMarkup, InlineKeyboardButton, InputMediaPhoto
from aiogram.exceptions import TelegramAPIError
from aiogram.filters import Command, CommandObject


BOT_TOKEN = "8818600279:AAE7L3WMzrWPZc-XvoGVefdqbUedpq2B_t4"
ADMIN_ID = 8452860496  

bot = Bot(token=BOT_TOKEN)
dp = Dispatcher()


USER_BALANCES = {}   
USED_PROMOS = {}    

PROMO_CODES = {
    "START20": 20.0,
    "PROJECT50": 50.0,
    "BONUS5": 5.0
}

CITIES = ["Cēsis", "Rīga"]

PRODUCTS = {
    "ekonomija": {
        "name": "📈Mājaslapas Izveide📈",
        "description": "Pilnībā pabeigta mājaslapa",
        "prices": {
            1: 200,
            3: 500,
            5: 700
        }
    },
    "enciklopedija": {
        "name": "📚Telegram Robots📚",
        "description": "Pilnībā uzbūvēts un automatizēts bots",
        "prices": {
            1: 100,   # 1 gab cena
            3: 260,   # 3 gab cena (no attēla)
            5: 370    # 5 gab cena (no attēla)
        }
    }
}

BUSINESS_PAYMENT_INFO = {
    "bank_name": "Swedbank",
    "account_name": "Alvis Silins",
    "iban": "LV30HABA0551055060654",
    "processing_time": "1-2 stundas"
}


def get_grid_menu_keyboard():
    return InlineKeyboardMarkup(inline_keyboard=[
        [
            InlineKeyboardButton(text="🛍 Shopping", callback_data="view_shop"),
            InlineKeyboardButton(text="💵 Add funds", callback_data="view_balance")
        ],
        [
            InlineKeyboardButton(text="🛒 Account", callback_data="view_account"),
            InlineKeyboardButton(text="☎️ Support", callback_data="view_support")
        ],
        [
            InlineKeyboardButton(text="📰 News", callback_data="view_news"),
            InlineKeyboardButton(text="🏆 Reviews", callback_data="view_reviews")
        ]
    ])

def get_back_keyboard():
    return InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text="⬅️ Atpakaļ uz galveno izvēlni", callback_data="main_menu")]
    ])



@dp.message(Command("start"))
async def cmd_start(message: Message):
    user_id = message.from_user.id
    if user_id not in USER_BALANCES:
        USER_BALANCES[user_id] = 0.0  
        
    await message.answer(
        f"👋 Sveiki, {message.from_user.first_name}!\n"
        f"",
        reply_markup=get_grid_menu_keyboard()
    )

@dp.callback_query(F.data == "main_menu")
async def go_to_main_menu(callback: CallbackQuery):
    await callback.message.edit_text(
        "👑 Galvenā izvēlne:",
        reply_markup=get_grid_menu_keyboard()
    )
    await callback.answer()



@dp.callback_query(F.data == "view_shop")
async def show_shop_cities(callback: CallbackQuery):
    buttons = []
    
    for i in range(0, len(CITIES), 2):
        row = [InlineKeyboardButton(text=f"📍 {CITIES[i]}", callback_data=f"shop_city:{i}")]
        if i + 1 < len(CITIES):
            row.append(InlineKeyboardButton(text=f"📍 {CITIES[i+1]}", callback_data=f"shop_city:{i+1}"))
        buttons.append(row)
    
    buttons.append([InlineKeyboardButton(text="⬅️ Atpakaļ", callback_data="main_menu")])
    keyboard = InlineKeyboardMarkup(inline_keyboard=buttons)
    
    await callback.message.edit_text("🌍 Izvēlieties pilsētu", reply_markup=keyboard)
    await callback.answer()

@dp.callback_query(F.data.startswith("shop_city:"))
async def show_city_products(callback: CallbackQuery):
    city_idx = int(callback.data.split(":")[1])
    city_name = CITIES[city_idx]
    
    buttons = []
    for prod_key, prod_data in PRODUCTS.items():
        buttons.append([InlineKeyboardButton(text=prod_data["name"], callback_data=f"shop_prod:{city_idx}:{prod_key}")])
        
    buttons.append([InlineKeyboardButton(text="⬅️ Atpakaļ uz pilsētām", callback_data="view_shop")])
    keyboard = InlineKeyboardMarkup(inline_keyboard=buttons)
    
    await callback.message.edit_text(f"📍 Pilsēta: {city_name}\n\nIzvēlieties kādu no pieejamajām precēm:", parse_mode="Markdown", reply_markup=keyboard)
    await callback.answer()

@dp.callback_query(F.data.startswith("shop_prod:"))
async def show_product_quantities(callback: CallbackQuery):
    data_parts = callback.data.split(":")
    city_idx = int(data_parts[1])
    prod_key = data_parts[2]
    
    city_name = CITIES[city_idx]
    product = PRODUCTS[prod_key]
    
    buttons = []
    for qty, price in product["prices"].items():
        buttons.append([InlineKeyboardButton(text=f"📦 {qty} gab ({price:.2f} €)", callback_data=f"buy:{city_idx}:{prod_key}:{qty}")])
        
    buttons.append([InlineKeyboardButton(text="⬅️ Atpakaļ uz precēm", callback_data=f"shop_city:{city_idx}")])
    keyboard = InlineKeyboardMarkup(inline_keyboard=buttons)
    
    text = (
        f"📋 {product['name']}\n"
        f"📍 Pilsēta: {city_name}\n\n"
        f"ℹ️ Apraksts: {product['description']}\n\n"
        f"Izvēlieties sev vēlamo daudzumu:"
    )
    await callback.message.edit_text(text, parse_mode="Markdown", reply_markup=keyboard)
    await callback.answer()

@dp.callback_query(F.data.startswith("buy:"))
async def process_purchase_new(callback: CallbackQuery):
    data_parts = callback.data.split(":")
    city_idx = int(data_parts[1])
    prod_key = data_parts[2]
    qty = int(data_parts[3])
    
    user_id = callback.from_user.id
    user_name = callback.from_user.full_name
    city_name = CITIES[city_idx]
    product = PRODUCTS[prod_key]
    price = product["prices"][qty]
    
    current_balance = USER_BALANCES.get(user_id, 0.0)
    
    if current_balance < price:
        await callback.answer(f"❌ Darījums noraidīts: Nepietiekams balanss! Nepieciešams {price:.2f}€", show_alert=True)
        return


    USER_BALANCES[user_id] = current_balance - price
    await callback.answer("✅ Pirkums veiksmīgs!", show_alert=True)
    await callback.message.delete()
    

    download_keyboard = InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text="📥 Lejupielādēt failus", url="https://example.com/download")],
        [InlineKeyboardButton(text="⬅️ Atpakaļ uz izvēlni", callback_data="main_menu")]
    ])
    
    await bot.send_message(
        chat_id=user_id,
        text=(
            f"🎉 Pirkums pabeigts!\n\n"
            f"Prece: {product['name']} ({qty} gab)\n"
            f"Pilsēta: {city_name}\n"
            f"Atskaitīts: -{price:.2f}€\n"
            f"Atlikušais balanss: {USER_BALANCES[user_id]:.2f}€\n\n"
            f"Lejupielādējiet savu digitālo saturu zemāk:"
        ),
        parse_mode="Markdown",
        reply_markup=download_keyboard
    )

    try:
        admin_notification = (
            f"🛍 Jauns pirkums veikalā!\n"
f"Pircējs: {user_name}\n"
            f"ID: {user_id}\n"
            f"Pilsēta: {city_name}\n"
            f"Prece: {product['name']} ({qty} gab)\n"
            f"Kopā samaksāts: {price:.2f}€\n\n"
            f"💬 *Atbildi tieši uz šo ziņu, lai nosūtītu personīgu vēstuli pircējam.*"
        )
        await bot.send_message(chat_id=ADMIN_ID, text=admin_notification, parse_mode="Markdown")
    except TelegramAPIError:
        logging.error("Admin paziņojums neizdevās.")


@dp.callback_query(F.data == "view_balance")
async def show_balance(callback: CallbackQuery):
    user_id = callback.from_user.id
    balance = USER_BALANCES.get(user_id, 0.0)
    
    keyboard = InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text="💳 Card Payment / Apple Pay", callback_data="pay_by_card")],
        [InlineKeyboardButton(text="🎟 PROMOCODE", callback_data="pay_by_promo")],
        [InlineKeyboardButton(text="⬅️ Atpakaļ", callback_data="main_menu")]
    ])
    
    await callback.message.edit_text(
        f"💵 Konta bilances papildināšana\n\n"
        f"Tavs pašreizējais balanss: {balance:.2f}€\n\n"
        f"Izvēlieties sev ērtāko papildināšanas veidu:",
        parse_mode="Markdown",
        reply_markup=keyboard
    )
    await callback.answer()

@dp.callback_query(F.data == "pay_by_promo")
async def pay_by_promo_prompt(callback: CallbackQuery):

    await callback.message.edit_text(
        "🎟 Papildināšana ar Promokodu\n\n"
        "Lai aktivizētu promokodu, lūdzu, ierakstiet čatā šādu komandu:\n\n"
        "👉 /promo JŪSU_KODS\n\n"
        "Piemēram: /promo START20",
        parse_mode="Markdown",
        reply_markup=InlineKeyboardMarkup(inline_keyboard=[
            [InlineKeyboardButton(text="⬅️ Atpakaļ", callback_data="view_balance")]
        ])
    )
    await callback.answer()

@dp.callback_query(F.data == "pay_by_card")
async def pay_by_card_details(callback: CallbackQuery):
    user_id = callback.from_user.id
    text = (
        f"💳 Kredītkartes un Bankas pārskaitījuma rekvizīti\n\n"
        f"Lai papildinātu kontu, veiciet pārskaitījumu uz mūsu autorizēto kontu:\n\n"
        f"🏢 Saņēmējs: {BUSINESS_PAYMENT_INFO['account_name']}\n"
        f"🏦 Banka: {BUSINESS_PAYMENT_INFO['bank_name']}\n"
        f"🔢 IBAN Konts: {BUSINESS_PAYMENT_INFO['iban']}\n"
        f"📌 Maksājuma mērķis (SVARĪGI): {user_id}\n\n"
        f"⚠️ UZMANĪBU: Maksājuma mērķī precīzi jānorāda Jūsu ID ({user_id}), lai mūsu sistēma automātiski zinātu, kuram lietotājam ieskaitīt naudu.\n\n"
        f"🕒 Apstrādes laiks: {BUSINESS_PAYMENT_INFO['processing_time']}. Pēc tam, kad administrators apstiprinās maksājumu, nauda būs Jūsu kontā."
    )
    await callback.message.edit_text(text, parse_mode="Markdown", reply_markup=InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text="⬅️ Atpakaļ", callback_data="view_balance")]
    ]))
    await callback.answer()



@dp.callback_query(F.data == "view_account")
async def show_account(callback: CallbackQuery):
    user_id = callback.from_user.id
    balance = USER_BALANCES.get(user_id, 0.0)
    text = (
        f"🛒 Jūsu profils\n\n"
        f"👤 Vārds: {callback.from_user.full_name}\n"
        f"🆔 Lietotāja ID: {user_id}\n"
        f"💰 Balanss: {balance:.2f}€\n"
        f"📊 Statuss: Aktīvs pircējs"
    )
    await callback.message.edit_text(text, parse_mode="Markdown", reply_markup=get_back_keyboard())
    await callback.answer()

@dp.callback_query(F.data == "view_support")
async def show_support(callback: CallbackQuery):
    text = (
        f"☎️ Atbalsta dienests\n\n"
        f"Nepieciešama palīdzība ar bankas maksājuma apstiprināšanu vai piekļuvi produktam ? \n\n"
        f"📩 Saziņa ar adminu: @Rukitis\n"
    )
    await callback.message.edit_text(text, parse_mode="Markdown", reply_markup=get_back_keyboard())
    await callback.answer()
@dp.callback_query(F.data == "view_news")
async def show_news(callback: CallbackQuery):
    text = (
        f"📰 test\n\n"
        f"🔥 test\n"
        f"• test\n"
        f"• test\n"
        f"• test"
    )
    await callback.message.edit_text(text, parse_mode="Markdown", reply_markup=get_back_keyboard())
    await callback.answer()

@dp.callback_query(F.data == "view_reviews")
async def show_reviews(callback: CallbackQuery):
    text = (
        f"🏆 Verificētas klientu atsauksmes\n\n"
        f"⭐️⭐️⭐️⭐️⭐️ skolotajs \n"
        f"_\"...\"_\n\n"
        f"⭐️⭐️⭐️⭐️⭐️ - skolotajs \n"
        f"_\"....\"_"
    )
    await callback.message.edit_text(text, parse_mode="Markdown", reply_markup=get_back_keyboard())
    await callback.answer()



@dp.message(Command("promo"))
async def redeem_promo(message: Message, command: CommandObject):
    user_id = message.from_user.id
    
    if not command.args:
        await message.answer("⚠️ Lūdzu, norādiet kodu. Piemērs: /promo START20", parse_mode="Markdown")
        return
        
    promo_code = command.args.strip().upper()
    
    if promo_code not in PROMO_CODES:
        await message.answer("❌ Nepareizs promokods. Pārbaudiet pareizrakstību un mēģiniet vēlreiz.")
        return
        
    if user_id in USED_PROMOS and promo_code in USED_PROMOS[user_id]:
        await message.answer("❌ Jūs jau esat vienu reizi izmantojis šo promokodu!")
        return
        
    reward_amount = PROMO_CODES[promo_code]
    USER_BALANCES[user_id] = USER_BALANCES.get(user_id, 0.0) + reward_amount
    
    if user_id not in USED_PROMOS:
        USED_PROMOS[user_id] = []
    USED_PROMOS[user_id].append(promo_code)
    
    await message.answer(
        f"🎉 Promokods pieņemts!\n\n"
        f"Ieskaitīts: +{reward_amount:.2f}€\n"
        f"Jūsu jaunais balanss: {USER_BALANCES[user_id]:.2f}€",
        parse_mode="Markdown"
    )

@dp.message(Command("addbalance"))
async def admin_add_balance(message: Message, command: CommandObject):
    if message.from_user.id != ADMIN_ID:
        return  
        
    if not command.args:
        await message.answer("⚠️ Formāts adminam: /addbalance LIETOTĀJA_ID SUMMA", parse_mode="Markdown")
        return
        
    try:
        args = command.args.split()
        target_user_id = int(args[0])
        amount = float(args[1])
        
        USER_BALANCES[target_user_id] = USER_BALANCES.get(target_user_id, 0.0) + amount
        await message.reply(f"✅ Gatavs! Lietotājam {target_user_id} pieskaitīti {amount:.2f}€.\nJaunā bilance: {USER_BALANCES[target_user_id]:.2f}€", parse_mode="Markdown")
        
        try:
            await bot.send_message(
                chat_id=target_user_id,
                text=f"💰 Balanss papildināts!\n\nAdministrators apstiprināja maksājumu un ieskaitīja {amount:.2f}€ Tavā kontā.\nKopējais balanss: {USER_BALANCES[target_user_id]:.2f}€",
                parse_mode="Markdown"
            )
        except TelegramAPIError:
            await message.answer("⚠️ Balanss atjaunots, bet neizdevās nosūtīt paziņojumu lietotājam.")
            
    except (ValueError, IndexError):
        await message.answer("❌ Kļūda. Pārliecinies par formātu: /addbalance 123456789 20.00")

@dp.message(F.reply_to_message & (F.from_user.id == ADMIN_ID))
async def send_personal_note(message: Message):
    if "Jauns pirkums veikalā!" in message.reply_to_message.text:
        match = re.search(r"ID:\s*(\d+)", message.reply_to_message.text)
        if match:
            buyer_id = int(match.group(1))
            personal_letter = message.text
            
            try:
                await bot.send_message(
                    chat_id=buyer_id, 
                    text=f"💌 Ziņa no veikala īpašnieka:\n\n_{personal_letter}_",
                    parse_mode="Markdown"
                )
                await message.reply("✅ Jūsu personīgā vēstule tika veiksmīgi nosūtīta pircējam!")
            except TelegramAPIError:
                await message.reply("❌ Neizdevās nosūtīt. Lietotājs var būt nobloķējis botu.")


async def main():
    logging.basicConfig(level=logging.INFO)
    print("🤖 Secure Grid Bot is successfully active and online...")
    await dp.start_polling(bot)

if __name__ == "__main__":
    asyncio.run(main())
