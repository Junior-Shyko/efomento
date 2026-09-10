<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import dayjs from 'dayjs';
import AppTextEditor from '@/Components/AppTextEditor.vue';
import SanitizedHtml from '@/Components/SanitizedHtml.vue';
import { useDiligenceMessages } from '@/Composables/useDiligenceMessages';
import { useSnackbar } from '@/Composables/useSnackbar';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    stage: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: 'Envie mensagem ao agente cultural.',
    },
    subject: {
        type: String,
        default: '',
    },
    canSend: {
        type: Boolean,
        default: true,
    },
});

const MIN_BODY_LENGTH = 20;

const stageLabels = {
    monitoramento: 'Monitoramento',
    prestacao_de_contas: 'Prestação de Contas',
};

const { messages, loading, sending, fetchMessages, send } = useDiligenceMessages(props.project.id, props.stage);
const { showSnackbar } = useSnackbar();

const body = ref('');
const threadEl = ref(null);
const stageUnavailable = ref(false);

const stageLabel = computed(() => stageLabels[props.stage] ?? props.stage);

const toEmail = computed(() => props.project.agent?.latest_snapshot?.email ?? null);

const computedSubject = computed(() => {
    if (props.subject) return props.subject;

    return `Diligência — ${stageLabel.value} — ${props.project.title_project ?? ''}`.trim();
});

const plainBody = computed(() =>
    body.value
        .replace(/<[^>]*>/g, ' ')
        .replace(/&nbsp;/g, ' ')
        .trim()
);

const canSubmit = computed(() => props.canSend && Boolean(toEmail.value) && plainBody.value.length >= MIN_BODY_LENGTH);

const isHtml = (value) => /<[a-z][\s\S]*>/i.test(value ?? '');

// Mantém uma conversa contínua em ordem cronológica, como nos aplicativos
// de mensagens: as mais antigas ficam no topo e as mais recentes no fim.
const threads = computed(() => {
    const orderedMessages = [...messages.value].sort(
        (a, b) => dayjs(a.sent_at ?? 0).valueOf() - dayjs(b.sent_at ?? 0).valueOf()
    );

    return orderedMessages.length ? [{ id: 'conversation', messages: orderedMessages }] : [];
});

const formatSentAt = (value) => (value ? dayjs(value).format('DD/MM/YYYY [às] HH:mm') : '');

async function scrollToBottom() {
    await nextTick();
    if (threadEl.value) {
        threadEl.value.scrollTop = threadEl.value.scrollHeight;
    }
}

async function submit() {
    if (!canSubmit.value || sending.value) return;

    try {
        await send({
            subject: computedSubject.value,
            body: body.value,
            to_email: toEmail.value,
        });
        body.value = '';
        showSnackbar('Mensagem enviada ao agente cultural.', 'success');
        scrollToBottom();
    } catch (error) {
        const errors = error.response?.data?.errors;
        const message = errors
            ? Object.values(errors).flat().join(' ')
            : 'Não foi possível enviar a mensagem. Tente novamente.';
        showSnackbar(message, 'error');
    }
}

watch(messages, scrollToBottom, { deep: true, flush: 'post' });

onMounted(async () => {
    try {
        await fetchMessages();
        scrollToBottom();
    } catch (error) {
        if (error.response.data.message === '') {
            stageUnavailable.value = false;
        } else {
            showSnackbar('Não foi possível carregar as mensagens da diligência.', 'error');
            stageUnavailable.value = true;
        }
    }
});
</script>

<template>
    <div v-if="stageUnavailable" class="space-y-3">
        <p class="text-sm text-gray-700">{{ description }}</p>
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-800">
            Os dados de {{ stageLabel }} deste projeto ainda não foram cadastrados. O envio de mensagens estará
            disponível assim que essa etapa for registrada.
        </div>
    </div>

    <div v-else class="space-y-3">
        <p class="text-sm text-gray-700">{{ description }}</p>

        <div ref="threadEl" class="bg-gray-100 rounded-lg p-4 max-h-96 overflow-y-auto space-y-3">
            <div v-if="loading" class="text-sm text-gray-500 text-center py-6">Carregando mensagens...</div>

            <div v-else-if="!messages.length" class="text-sm text-gray-500 text-center py-6">
                Nenhuma mensagem enviada até o momento.
            </div>

            <template v-else>
                <div
                    v-for="(thread, threadIndex) in threads"
                    :key="thread.id"
                    class="space-y-3"
                    :class="threadIndex > 0 ? 'pt-3 border-t border-gray-300' : ''"
                >
                    <div
                        v-for="message in thread.messages"
                        :key="message.id"
                        class="flex"
                        :class="message.direction === 'OUTBOUND' ? 'justify-end' : 'justify-start'"
                    >
                        <div
                            class="max-w-[85%] rounded-lg p-3 text-sm shadow-sm"
                            :class="message.direction === 'OUTBOUND' ? 'bg-white' : 'bg-amber-50'"
                        >
                            <SanitizedHtml
                                v-if="message.direction === 'OUTBOUND' && isHtml(message.body)"
                                :content="message.body"
                                class="text-gray-800 message-body"
                            />
                            <p v-else class="whitespace-pre-line text-gray-800">{{ message.body }}</p>
                            <div v-if="message.attachments?.length" class="mt-3 pt-2 border-t border-gray-200">
                                <p class="text-xs font-semibold text-gray-600 mb-1">Anexos</p>
                                <a
                                    v-for="attachment in message.attachments"
                                    :key="attachment.id"
                                    :href="attachment.url"
                                    class="flex items-center gap-1 text-xs text-blue-700 hover:underline"
                                >
                                    <v-icon icon="mdi-paperclip" size="14" />
                                    <span class="break-all">{{ attachment.name }}</span>
                                </a>
                            </div>
                            <p class="text-xs text-gray-500 text-right mt-2 font-semibold">
                                {{ message.direction === 'OUTBOUND' ? 'Enviada em' : 'Recebida em' }}
                                {{ formatSentAt(message.sent_at) }}
                            </p>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <AppTextEditor
            v-model="body"
            placeholder="Escreva uma mensagem aqui..."
            :height="220"
            :menubar="false"
            :statusbar="false"
            toolbar-location="bottom"
            :plugins="['link', 'lists', 'image', 'autolink']"
            toolbar="undo redo | bold italic underline strikethrough | forecolor | link image"
        />

        <div class="flex items-center justify-between">
            <p v-if="!canSend" class="text-xs text-red-600">
                Você não tem permissão para enviar mensagens nesta etapa.
            </p>
            <p v-else-if="!toEmail" class="text-xs text-red-600">O agente cultural não possui e-mail cadastrado.</p>
            <p v-else-if="plainBody.length < 20 && plainBody.length < MIN_BODY_LENGTH" class="text-black font-bold">
                Para enviar, digite um número mínimo de {{ MIN_BODY_LENGTH }} caracteres.
            </p>
            <span v-else />

            <v-btn color="primary" append-icon="mdi-send" :disabled="!canSend" :loading="sending" @click="submit">
                Enviar mensagem
            </v-btn>
        </div>
    </div>
</template>

<style scoped>
.message-body :deep(p) {
    margin-bottom: 0.5rem;
}

.message-body :deep(p:last-child) {
    margin-bottom: 0;
}
</style>
