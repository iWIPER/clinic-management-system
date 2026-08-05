import { ref } from 'vue';

// Mesma forma de useCepLookup.js (loading/lookup), sem cache — cada telefone
// precisa ser checado fresco (BRD PATIENT_INVITATIONS §7/§18).
export function usePhoneDuplicateCheck() {
    const loading = ref(false);
    const patient = ref(null);
    const activeInvite = ref(null);
    const checked = ref(false);

    const reset = () => {
        patient.value = null;
        activeInvite.value = null;
        checked.value = false;
    };

    const lookup = async (telefone) => {
        const value = (telefone || '').trim();
        if (!value) {
            reset();
            return;
        }
        if (loading.value) return;

        loading.value = true;
        try {
            const { data } = await window.axios.get(route('patient-invites.check-phone'), {
                params: { telefone: value },
            });
            patient.value = data.patient;
            activeInvite.value = data.active_invite;
            checked.value = true;
        } catch {
            reset();
        } finally {
            loading.value = false;
        }
    };

    return { loading, patient, activeInvite, checked, lookup, reset };
}
