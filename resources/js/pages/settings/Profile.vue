<script setup lang="ts">
import { TransitionRoot } from '@headlessui/vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import UserAvatar from '@/components/project/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem, type SharedData, type User } from '@/types';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
    className?: string;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: '/settings/profile',
    },
];

const page = usePage<SharedData>();
const user = page.props.auth.user as User;

const form = useForm({
    name: user.name,
    email: user.email,
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};

// Photo de profil : envoyée dès qu'elle est choisie.
const me = computed(() => page.props.auth.user);
const photoInput = ref<HTMLInputElement>();
const photoForm = useForm({ photo: null as File | null });
const uploadPhoto = (e: Event) => {
    photoForm.photo = (e.target as HTMLInputElement).files?.[0] ?? null;
    if (photoForm.photo)
        photoForm.post(route('photo.update'), { preserveScroll: true, onFinish: () => photoInput.value && (photoInput.value.value = '') });
};
const removePhoto = () => router.delete(route('photo.destroy'), { preserveScroll: true });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Profil" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall title="Photo de profil" description="Elle remplace votre avatar auprès de vos collègues" />
                <div class="flex items-center gap-4">
                    <UserAvatar :user="me" size="xl" />
                    <div class="space-y-1.5">
                        <div class="flex gap-2">
                            <Button type="button" variant="outline" size="sm" :disabled="photoForm.processing" @click="photoInput?.click()">
                                {{ me.avatar ? 'Changer la photo' : 'Ajouter une photo' }}
                            </Button>
                            <Button v-if="me.avatar" type="button" variant="ghost" size="sm" @click="removePhoto">Retirer</Button>
                        </div>
                        <p class="text-xs text-muted-foreground">JPG, PNG ou WebP, 5 Mo maximum.</p>
                        <InputError :message="photoForm.errors.photo" />
                    </div>
                    <input ref="photoInput" type="file" accept="image/*" class="hidden" @change="uploadPhoto" />
                </div>
            </div>

            <div class="flex flex-col space-y-6">
                <HeadingSmall title="Informations du profil" description="Modifiez votre nom et votre adresse e-mail" />

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="name">Nom</Label>
                        <Input id="name" class="mt-1 block w-full" v-model="form.name" required autocomplete="name" placeholder="Prénom Nom" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Adresse e-mail</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            v-model="form.email"
                            required
                            autocomplete="username"
                            placeholder="Adresse e-mail"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="mt-2 text-sm text-neutral-800">
                            Votre adresse e-mail n'est pas vérifiée.
                            <Link
                                :href="route('verification.send')"
                                method="post"
                                as="button"
                                class="focus:outline-hidden rounded-md text-sm text-neutral-600 underline hover:text-neutral-900 focus:ring-2 focus:ring-offset-2"
                            >
                                Cliquez ici pour renvoyer l'e-mail de vérification.
                            </Link>
                        </p>

                        <div v-if="status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-green-600">
                            Un nouveau lien de vérification vous a été envoyé.
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing">Enregistrer</Button>

                        <TransitionRoot
                            :show="form.recentlySuccessful"
                            enter="transition ease-in-out"
                            enter-from="opacity-0"
                            leave="transition ease-in-out"
                            leave-to="opacity-0"
                        >
                            <p class="text-sm text-neutral-600">Enregistré.</p>
                        </TransitionRoot>
                    </div>
                </form>
            </div>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
