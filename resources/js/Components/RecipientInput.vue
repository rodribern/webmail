<script setup>
import { ref, computed, watch, nextTick } from 'vue';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    contacts: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'Adicionar destinatário...',
    },
    label: {
        type: String,
        default: '',
    },
    primaryColor: {
        type: String,
        default: '#3B82F6',
    },
});

const emit = defineEmits(['update:modelValue']);

const inputText = ref('');
const inputEl = ref(null);
const isFocused = ref(false);
const selectedSuggestion = ref(-1);

const suggestions = computed(() => {
    const query = inputText.value.toLowerCase().trim();
    if (!query || query.length < 2) return [];

    const existingEmails = props.modelValue.map(r => r.email.toLowerCase());

    return props.contacts
        .filter(c => {
            if (existingEmails.includes(c.email.toLowerCase())) return false;
            return c.email.toLowerCase().includes(query) ||
                   (c.name && c.name.toLowerCase().includes(query));
        })
        .slice(0, 8);
});

const showSuggestions = computed(() => {
    return isFocused.value && suggestions.value.length > 0;
});

const addRecipient = (recipient) => {
    const email = recipient.email?.trim();
    if (!email) return;

    // Verifica se já existe
    if (props.modelValue.some(r => r.email.toLowerCase() === email.toLowerCase())) return;

    emit('update:modelValue', [...props.modelValue, { email, name: recipient.name || '' }]);
    inputText.value = '';
    selectedSuggestion.value = -1;
    nextTick(() => inputEl.value?.focus());
};

const addFromInput = () => {
    const text = inputText.value.trim();
    if (!text) return;

    // Se tem uma sugestão selecionada, usa ela
    if (selectedSuggestion.value >= 0 && suggestions.value[selectedSuggestion.value]) {
        addRecipient(suggestions.value[selectedSuggestion.value]);
        return;
    }

    // Tenta extrair email do texto
    const emailMatch = text.match(/<?([^\s<>]+@[^\s<>]+)>?/);
    if (emailMatch) {
        addRecipient({ email: emailMatch[1], name: '' });
    }
};

const removeRecipient = (index) => {
    const updated = [...props.modelValue];
    updated.splice(index, 1);
    emit('update:modelValue', updated);
};

const handleKeydown = (e) => {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        addFromInput();
    } else if (e.key === 'Tab') {
        // Só intercepta TAB se há texto para converter em chip
        if (inputText.value.trim()) {
            e.preventDefault();
            addFromInput();
        }
        // Senão, deixa o TAB navegar para o próximo campo
    } else if (e.key === 'Backspace' && !inputText.value && props.modelValue.length > 0) {
        removeRecipient(props.modelValue.length - 1);
    } else if (e.key === 'ArrowDown' && showSuggestions.value) {
        e.preventDefault();
        selectedSuggestion.value = Math.min(selectedSuggestion.value + 1, suggestions.value.length - 1);
    } else if (e.key === 'ArrowUp' && showSuggestions.value) {
        e.preventDefault();
        selectedSuggestion.value = Math.max(selectedSuggestion.value - 1, -1);
    } else if (e.key === 'Escape') {
        isFocused.value = false;
    }
};

const handleBlur = () => {
    // Delay para permitir clique na sugestão
    setTimeout(() => {
        isFocused.value = false;
        if (inputText.value.trim()) {
            addFromInput();
        }
    }, 200);
};

watch(inputText, () => {
    selectedSuggestion.value = -1;
});
</script>

<template>
    <div class="relative">
        <div
            class="flex flex-wrap items-center gap-1.5 px-3 py-2 border border-gray-300 rounded-xl bg-white min-h-[42px] cursor-text focus-within:ring-2 focus-within:border-transparent transition-shadow"
            :style="{ '--tw-ring-color': primaryColor + '40' }"
            @click="inputEl?.focus()"
        >
            <!-- Label -->
            <span v-if="label" class="text-sm text-gray-500 mr-1 select-none">{{ label }}</span>

            <!-- Chips -->
            <span
                v-for="(recipient, index) in modelValue"
                :key="recipient.email"
                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-sm bg-gray-100 text-gray-700 max-w-[250px]"
            >
                <span class="truncate" :title="recipient.name ? `${recipient.name} <${recipient.email}>` : recipient.email">
                    {{ recipient.name || recipient.email }}
                </span>
                <button
                    type="button"
                    @click.stop="removeRecipient(index)"
                    class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition-colors"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </span>

            <!-- Input -->
            <input
                ref="inputEl"
                v-model="inputText"
                type="text"
                :placeholder="modelValue.length === 0 ? placeholder : ''"
                class="flex-1 min-w-[120px] text-sm border-0 p-0 focus:ring-0 bg-transparent outline-none"
                @keydown="handleKeydown"
                @focus="isFocused = true"
                @blur="handleBlur"
            />
        </div>

        <!-- Sugestões -->
        <div
            v-if="showSuggestions"
            class="absolute left-0 right-0 mt-1 bg-white rounded-xl shadow-lg border z-30 max-h-48 overflow-y-auto"
        >
            <button
                v-for="(contact, index) in suggestions"
                :key="contact.email"
                type="button"
                @mousedown.prevent="addRecipient(contact)"
                class="w-full px-4 py-2.5 text-left text-sm hover:bg-gray-50 transition-colors flex items-center space-x-3"
                :class="{ 'bg-gray-50': index === selectedSuggestion }"
            >
                <div
                    class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-medium flex-shrink-0"
                    :style="{ backgroundColor: primaryColor }"
                >
                    {{ (contact.name || contact.email).charAt(0).toUpperCase() }}
                </div>
                <div class="min-w-0">
                    <div v-if="contact.name" class="text-gray-900 truncate">{{ contact.name }}</div>
                    <div class="text-gray-500 text-xs truncate">{{ contact.email }}</div>
                </div>
            </button>
        </div>
    </div>
</template>
