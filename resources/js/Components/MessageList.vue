<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    messages: {
        type: Array,
        default: () => []
    },
    currentFolder: {
        type: String,
        default: 'INBOX'
    },
    primaryColor: {
        type: String,
        default: '#3B82F6'
    },
    selectedUids: {
        type: Set,
        default: () => new Set()
    }
});

const emit = defineEmits(['toggle-select', 'toggle-select-all']);

// Cores para avatares baseado na inicial
const avatarColors = [
    '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
    '#EC4899', '#06B6D4', '#84CC16', '#F97316', '#6366F1'
];

const getAvatarColor = (name) => {
    if (!name) return avatarColors[0];
    const charCode = name.charCodeAt(0);
    return avatarColors[charCode % avatarColors.length];
};

const getInitial = (from) => {
    if (!from) return '?';
    const name = from.name || from.email || '';
    return name.charAt(0).toUpperCase() || '?';
};

const formatDate = (dateString) => {
    if (!dateString) return '';

    const date = new Date(dateString);
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const messageDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());

    // Hoje: apenas hora
    if (messageDate.getTime() === today.getTime()) {
        return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }

    // Este ano: dia/mês
    if (date.getFullYear() === now.getFullYear()) {
        return date.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' });
    }

    // Anos anteriores: dia/mês/ano
    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit' });
};

const truncate = (text, length = 80) => {
    if (!text) return '';
    return text.length > length ? text.substring(0, length) + '...' : text;
};

const getMessageUrl = (message) => {
    return `/mail/${encodeURIComponent(props.currentFolder)}/${message.uid}`;
};

const formatFrom = (from) => {
    if (!from) return 'Desconhecido';
    if (from.name) return from.name;
    return from.email || 'Desconhecido';
};

const isAllSelected = () => {
    if (props.messages.length === 0) return false;
    return props.messages.every(m => props.selectedUids.has(m.uid));
};

const onCheckboxClick = (e, uid) => {
    e.stopPropagation();
    emit('toggle-select', uid);
};

const onSelectAllClick = () => {
    emit('toggle-select-all');
};
</script>

<template>
    <div class="p-3 space-y-2" :style="{ '--primary-bg': primaryColor + '15', '--primary-bg-hover': primaryColor + '40', '--primary-color': primaryColor, '--primary-ring': primaryColor + '60' }">
        <!-- Select all header -->
        <div v-if="messages.length > 0" class="flex items-center px-3 py-1">
            <label class="flex items-center cursor-pointer" @click.stop>
                <input
                    type="checkbox"
                    :checked="isAllSelected()"
                    @change="onSelectAllClick"
                    class="w-4 h-4 rounded border-gray-300 cursor-pointer branded-checkbox"
                />
                <span class="ml-2 text-xs text-gray-500">Selecionar todas</span>
            </label>
        </div>

        <!-- Empty state -->
        <div v-if="messages.length === 0" class="px-4 py-16 text-center">
            <div class="mx-auto w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                <svg class="h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            </div>
            <p class="text-gray-500 font-medium">Nenhuma mensagem</p>
            <p class="text-gray-400 text-sm mt-1">Esta pasta está vazia.</p>
        </div>

        <!-- Message items -->
        <div
            v-for="message in messages"
            :key="message.uid"
            class="flex items-start gap-2"
        >
            <!-- Checkbox -->
            <div class="flex items-center pt-3 pl-1 flex-shrink-0">
                <input
                    type="checkbox"
                    :checked="selectedUids.has(message.uid)"
                    @click="onCheckboxClick($event, message.uid)"
                    class="w-4 h-4 rounded border-gray-300 cursor-pointer branded-checkbox"
                />
            </div>

            <!-- Message Link -->
            <Link
                :href="getMessageUrl(message)"
                class="group flex-1 flex items-start p-3 rounded-xl transition-all duration-200 ease-out cursor-pointer message-card"
                :class="{
                    'shadow-md hover:shadow-lg hover:scale-[1.005]': !message.seen,
                    'shadow hover:shadow-md': message.seen,
                    'selected-message': selectedUids.has(message.uid)
                }"
            >
                <!-- Conteúdo principal -->
                <div class="flex-1 min-w-0">
                    <!-- Linha 1: Remetente + Data -->
                    <div class="flex items-center justify-between mb-0.5">
                        <span
                            class="text-sm truncate"
                            :class="{ 'font-semibold text-gray-900': !message.seen, 'font-medium text-gray-600': message.seen }"
                        >
                            {{ formatFrom(message.from) }}
                        </span>
                        <span
                            class="text-xs flex-shrink-0 ml-2"
                            :class="{ 'font-semibold text-gray-700': !message.seen, 'text-gray-400': message.seen }"
                        >
                            {{ formatDate(message.date) }}
                        </span>
                    </div>

                    <!-- Linha 2: Assunto -->
                    <div class="flex items-center">
                        <span
                            class="text-sm truncate"
                            :class="{ 'font-medium text-gray-800': !message.seen, 'text-gray-700': message.seen }"
                        >
                            {{ message.subject || '(Sem assunto)' }}
                        </span>
                    </div>

                    <!-- Linha 3: Preview + Indicadores -->
                    <div class="flex items-center mt-0.5">
                        <span class="text-xs text-gray-400 truncate flex-1">
                            {{ truncate(message.preview, 100) }}
                        </span>

                        <!-- Indicadores -->
                        <div class="flex items-center ml-2 space-x-1.5 flex-shrink-0">
                            <!-- Anexo -->
                            <div
                                v-if="message.has_attachments"
                                class="p-1 rounded bg-gray-100 text-gray-500"
                                title="Tem anexos"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                </svg>
                            </div>

                            <!-- Não lido -->
                            <div
                                v-if="!message.seen"
                                class="w-2 h-2 rounded-full"
                                :style="{ backgroundColor: primaryColor }"
                            ></div>

                            <!-- Favorito -->
                            <svg
                                v-if="message.flagged"
                                class="h-4 w-4 text-yellow-400"
                                fill="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </Link>
        </div>
    </div>
</template>

<style scoped>
.message-card {
    background-color: var(--primary-bg);
}
.message-card:hover {
    background-color: var(--primary-bg-hover);
}
.selected-message {
    ring: 2px solid var(--primary-ring);
    box-shadow: 0 0 0 2px var(--primary-ring);
}
.branded-checkbox {
    accent-color: var(--primary-color);
}
.branded-checkbox:focus {
    outline-color: var(--primary-color);
    box-shadow: 0 0 0 2px var(--primary-ring);
}
</style>
