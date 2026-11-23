<template>
    <div>
        <h1 v-if="$slots.header" class="bx-title mb-8">
            <slot name="header" />
        </h1>
        <slot />
    </div>
</template>
