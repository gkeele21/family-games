<template>
    <div class="flex items-center leading-tight">
        <template v-if="$page.props.auth.user">
            <Icon name="circle-user" class="text-xl text-gray-400 mx-4" />
            <span class="whitespace-nowrap">
                <Dropdown align="right" width="48" :content-classes="['p-4', 'text-center', 'text-muted']">
                    <template #trigger>
                        <div class="cursor-pointer">
                            <h3 class="font-semibold text-primary">{{ $page.props.auth.user.first_name }}</h3>
                            <h3 v-if="$page.props.auth.user.orgName" class="text-warning font-semibold">{{ $page.props.auth.user.orgName }}</h3>
                        </div>
                    </template>

                    <template #content>
                        <h1 class="mb-4">{{ $page.props.auth.user?.first_name + ' ' + $page.props.auth.user?.last_name }}</h1>
                        <Button @click="logout()">LOG OUT</Button>
                    </template>
                </Dropdown>
            </span>
        </template>

        <template v-else>
            <Link href="/login">
                <label class="px-2 py-1 bg-warning text-white rounded-xl cursor-pointer text-sm">LOGIN</label>
            </Link>
        </template>
    </div>
</template>

<script setup>
    import { Link, useForm } from '@inertiajs/vue3';
    import Button from '@/Components/Base/Button.vue';
    import Dropdown from '@/Components/Form/Dropdown.vue';
    import Icon from '@/Components/Base/Icon.vue';

    const form = useForm({});

    function logout() {
        form.post(route('logout'));
    }
</script>
