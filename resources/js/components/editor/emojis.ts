/** Emojis proposés par le sélecteur, par catégorie. */
export const EMOJI_GROUPS: { name: string; icon: string; emojis: string }[] = [
    {
        name: 'Smileys',
        icon: '😀',
        emojis: '😀 😃 😄 😁 😆 😅 🤣 😂 🙂 🙃 😉 😊 😇 🥰 😍 🤩 😘 😋 😛 😜 🤪 😝 🤗 🤭 🤫 🤔 🤐 🤨 😐 😑 😶 😏 😒 🙄 😬 😌 😔 😪 😴 😷 🤒 🤕 🤢 🤧 🥵 🥶 😵 🤯 🤠 🥳 😎 🤓 🧐 😕 😟 🙁 😮 😯 😲 😳 🥺 😦 😧 😨 😰 😥 😢 😭 😱 😖 😣 😞 😓 😩 😫 🥱 😤 😡 😠 🤬 😈 💀 💩 🤡 👻 🤖',
    },
    {
        name: 'Gestes',
        icon: '👍',
        emojis: '👍 👎 👌 🤌 ✌️ 🤞 🤟 🤘 🤙 👈 👉 👆 👇 ☝️ ✋ 🤚 🖐️ 🖖 👋 👏 🙌 👐 🤲 🤝 🙏 ✍️ 💪 🦾 👀 🧠 🫶 ❤️ 🧡 💛 💚 💙 💜 🖤 🤍 💔 ❣️ 💕 💯 💢 💥 💫 💦 💬 💭 💤',
    },
    {
        name: 'Travail',
        icon: '💼',
        emojis: '💼 📁 📂 🗂️ 📅 📆 🗓️ 📇 📈 📉 📊 📋 📌 📍 📎 🖇️ 📏 📐 ✂️ 🗃️ 🗄️ 🗑️ 🔒 🔓 🔑 🔨 🛠️ ⚙️ 🧰 🧲 💡 🔦 📚 📖 📕 📗 📘 📙 📓 📒 📃 📄 📑 🔖 🏷️ ✏️ ✒️ 🖊️ 🖋️ 🖍️ 📝 💻 🖥️ 🖨️ ⌨️ 🖱️ 💾 💿 📷 📸 🎥 📞 ☎️ 📱 📧 📨 📩 📤 📥 📦 📫 ⏰ ⏳ ⌛ ⏱️',
    },
    {
        name: 'Symboles',
        icon: '✅',
        emojis: '✅ ☑️ ✔️ ❌ ❎ ➕ ➖ ➗ ✖️ ❓ ❔ ❗ ❕ ‼️ ⁉️ ⚠️ 🚫 ⛔ 🔴 🟠 🟡 🟢 🔵 🟣 ⚫ ⚪ 🟥 🟧 🟨 🟩 🟦 🟪 ⬛ ⬜ 🔶 🔷 🔺 🔻 ⭐ 🌟 ✨ ⚡ 🔥 🎯 🏁 🚩 🔔 🔕 📣 📢 ♻️ 🆕 🆗 🆙 🆒 🆓 ℹ️ ➡️ ⬅️ ⬆️ ⬇️ ↩️ ↪️ 🔄 🔁 🔜',
    },
    {
        name: 'Fêtes',
        icon: '🎉',
        emojis: '🎉 🎊 🎈 🎁 🎀 🎂 🍰 🧁 🥂 🍾 🍻 🍺 🍷 ☕ 🍵 🍕 🍔 🍟 🌮 🥐 🍎 🍓 🍒 🍋 🍉 🍇 🥑 🥕 🌽 🍫 🍪 🍩 🏆 🥇 🥈 🥉 🏅 🎖️ ⚽ 🏀 🎾 🎮 🎲 🎨 🎬 🎤 🎧 🎵 🎶 🎸',
    },
    {
        name: 'Nature',
        icon: '🌿',
        emojis: '☀️ 🌤️ ⛅ 🌥️ ☁️ 🌧️ ⛈️ 🌩️ ❄️ ☃️ 🌈 🌙 ⭐ 🌍 🌸 🌼 🌻 🌹 🌷 🌱 🌿 🍀 🍁 🍂 🌲 🌳 🌴 🌵 🐶 🐱 🐭 🐹 🐰 🦊 🐻 🐼 🐨 🐯 🦁 🐮 🐷 🐸 🐵 🐔 🐧 🐦 🦄 🐝 🦋 🐢 🐙 🐬 🐳',
    },
    {
        name: 'Voyages',
        icon: '🚀',
        emojis: '🚀 ✈️ 🛫 🛬 🚗 🚕 🚌 🚚 🚛 🚲 🛵 🚂 🚢 ⛵ 🚁 🗺️ 🧭 🏠 🏡 🏢 🏬 🏭 🏗️ 🏛️ 🏰 🗼 🗽 ⛰️ 🏔️ 🏖️ 🏝️ 🌋 🏕️ 🇫🇷 🇪🇺 🇬🇧 🇺🇸 🇪🇸 🇮🇹 🇩🇪 🇧🇪 🇨🇭 🇨🇦',
    },
];

export function emojiList(group: { emojis: string }): string[] {
    return group.emojis.split(' ');
}
