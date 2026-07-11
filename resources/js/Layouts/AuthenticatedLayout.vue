<template>
    <div>
        <h2 v-if="$slots.header" class="mb-6 text-xl font-bold text-zinc-100">
            <slot name="header" />
        </h2>
        <slot />
    </div>
</template>
