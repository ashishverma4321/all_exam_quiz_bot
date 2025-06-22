import os
import random
import openai
import asyncio

from telegram import Update
from telegram.ext import (
    ApplicationBuilder,
    CommandHandler,
    ContextTypes,
    PollAnswerHandler
)

# Optional: For Replit
try:
    from keep_alive import keep_alive
    keep_alive()
except:
    pass

# ?? ? ???? Bot ?? ??? Token
TOKEN = "7647616718:AAFUBtWaLAsPRNVotkxJUQqj7HVZe-zBCEA"

# ?? OpenAI API key – ???? key ???? ???
openai.api_key = "your_openai_api_key_here"

# ????? ?? quiz ?????? ?? ????? ???? ?? ???
user_quiz = {}

# ?????????? ?????
subjects = ["General Knowledge", "Electronics", "Mathematics", "Reasoning"]

# AI ?? ?? ???? ???? ???? ??????
async def get_ai_question(subject):
    prompt = f"""Generate one multiple-choice question for {subject} in Hindi for a UP government exam. 
    Include: 
    - question
    - 4 options as a list
    - correct answer index (0-3)
    - a 100-word explanation in Hindi.
    Format as JSON like:
    {{
      "question": "?????? ???? ???",
      "options": ["?????? 1", "?????? 2", "?????? 3", "?????? 4"],
      "answer": 1,
      "explanation": "?? ?????? ??? ????? ?? ???? 100 ?????? ??? ??????"
    }}
    """
    try:
        response = openai.ChatCompletion.create(
            model="gpt-4",
            messages=[{"role": "user", "content": prompt}]
        )
        content = response["choices"][0]["message"]["content"]
        return eval(content)
    except Exception as e:
        print("? Error getting question:", e)
        return None

# /start ????? ?? quiz ???? ????
async def start(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user_id = update.effective_user.id
    chat_id = update.effective_chat.id

    user_quiz[user_id] = {
        "questions": [],
        "index": 0,
        "chat_id": chat_id
    }

    await context.bot.send_message(chat_id=chat_id, text="?? ????? ????????? ????, ?????? ????? ??? ?? ??? ???...")

    for _ in range(5):  # ???? 5 ?????? ??? (???? ?? ???)
        subject = random.choice(subjects)
        question = await get_ai_question(subject)
        if question:
            user_quiz[user_id]["questions"].append(question)

    await send_next_question(user_id, context)

# ???? ?????? ????? ???? ??????
async def send_next_question(user_id, context):
    quiz = user_quiz.get(user_id)
    if not quiz or quiz["index"] >= len(quiz["questions"]):
        await context.bot.send_message(chat_id=quiz["chat_id"], text="?? Quiz ?????? ???!")
        return

    q = quiz["questions"][quiz["index"]]
    question = q["question"]
    options = q["options"]
    correct_option_id = q["answer"]

    poll_message = await context.bot.send_poll(
        chat_id=quiz["chat_id"],
        question=question,
        options=options,
        type="quiz",
        correct_option_id=correct_option_id,
        is_anonymous=False,
        open_period=15
    )

    quiz["poll_id"] = poll_message.poll.id
    quiz["message_id"] = poll_message.message_id

# ?? ????? ???? ???? ??
async def handle_poll_answer(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user_id = update.poll_answer.user.id
    quiz = user_quiz.get(user_id)
    if not quiz:
        return

    index = quiz["index"]
    q = quiz["questions"][index]
    selected = update.poll_answer.option_ids[0]
    correct = q["answer"]

    explanation = q.get("explanation", "??? ???????? ?????? ???? ???")
    if selected == correct:
        msg = "? ??? ?????!\n"
    else:
        msg = f"? ??? ?????! ??? ?????: {q['options'][correct]}\n"
    msg += f"?? Explanation:\n{explanation}"

    await context.bot.send_message(chat_id=quiz["chat_id"], text=msg)

    quiz["index"] += 1
    await asyncio.sleep(1)
    await send_next_question(user_id, context)

# Bot ?? ?????
if __name__ == "__main__":
    app = ApplicationBuilder().token(TOKEN).build()
    app.add_handler(CommandHandler("start", start))
    app.add_handler(PollAnswerHandler(handle_poll_answer))

    print("?? Bot ???? ??...")
    app.run_polling()
