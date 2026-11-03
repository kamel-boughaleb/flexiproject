<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { MailPlus } from 'lucide-vue-next';
import { ref } from 'vue';

/** Bouton « Inviter un collaborateur » (administrateurs) : envoie l'e-mail de création de compte. */
const open = ref(false);
const sent = ref<string | null>(null);
const form = useForm({ email: '', name: '' });
const submit = () =>
    form.post(route('team.store'), {
        preserveScroll: true,
        onSuccess: () => {
            sent.value = form.email;
            form.reset();
        },
    });
const show = () => {
    sent.value = null;
    open.value = true;
};
</script>

<template>
    <Button variant="outline" @click="show"><MailPlus class="size-4" /> {{ $t('Inviter un collaborateur') }}</Button>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ $t('Inviter un collaborateur') }}</DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'La personne reçoit un e-mail pour créer son compte, ajouter sa photo et choisir un mot de passe (ou se connecter avec Google).',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="invite-email">{{ $t('E-mail') }}</Label>
                    <Input id="invite-email" v-model="form.email" type="email" required placeholder="collegue@flexilivre.com" />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-2">
                    <Label for="invite-name">{{ $t('Nom (facultatif)') }}</Label>
                    <Input id="invite-name" v-model="form.name" :placeholder="$t('Prénom Nom')" />
                </div>
                <p v-if="sent" class="text-sm font-bold text-success-text">
                    {{ $t('Invitation envoyée à :email. Vous pouvez en envoyer une autre.', { email: sent }) }}
                </p>
                <Button type="submit" :disabled="form.processing">{{ $t("Envoyer l'invitation") }}</Button>
            </form>
        </DialogContent>
    </Dialog>
</template>
