// Camino de cada equipo en la llave: une el duelo que gano con el siguiente que juega.
// Se busca por participante y no por posicion, porque los cruces pueden reordenarse entre rondas.
export function bracketRoutes(bracket) {
    const losers = new Set();
    for (const round of bracket) {
        for (const match of round.matches) {
            if (match.is_bye || !match.winner_entry_id) continue;
            losers.add(match.winner_entry_id === match.entry_one_id ? match.entry_two_id : match.entry_one_id);
        }
    }

    const routes = [];
    for (let i = 1; i < bracket.length; i++) {
        for (const match of bracket[i].matches) {
            for (const entry of [match.entry_one_id, match.entry_two_id]) {
                const from = entry && bracket[i - 1].matches.find((previous) => previous.winner_entry_id === entry);
                if (from) routes.push({ from: from.id, to: match.id, entry, alive: !losers.has(entry) });
            }
        }
    }
    return routes;
}
