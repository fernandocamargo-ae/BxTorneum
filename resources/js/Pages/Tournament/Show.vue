<script setup>
// Placeholder page: full UI implemented in Task 16 (Show page frontend).
// Exists now only so Inertia's page-component-exists check (and the Task 15
// backend tests, which assert `->component('Tournament/Show')`) resolve to a
// real file — mirrors the Task 8/10 precedent of registering the route ahead
// of its controller method.
defineProps({
    tournament: Object,
    has_tournament_deck: Boolean,
    my_entry_id: [Number, null],
    entries: Array,
    standings: Array,
    current_round_matches: Array,
});
</script>

<template>
    <div></div>
</template>
