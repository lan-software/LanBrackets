<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid, Trophy } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { home, dashboard } from '@/routes';
import { index as competitionsIndex } from '@/routes/competitions';
import type { NavItem } from '@/types';

const { t } = useI18n();
const page = usePage();
const user = computed(() => page.props.auth?.user as any);
const canManageBrackets = computed(() =>
    ['moderator', 'admin', 'superadmin'].includes(user.value?.role ?? ''),
);

const mainNavItems = computed((): NavItem[] => {
    const items: NavItem[] = [
        {
            title: t('navigation.dashboard'),
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    if (canManageBrackets.value) {
        items.push({
            title: t('navigation.competitions'),
            href: competitionsIndex(),
            icon: Trophy,
        });
    }

    return items;
});

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="home()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
