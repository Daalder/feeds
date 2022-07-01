<template>
    <div class="p-5">
        <div class="flex justify-center flex-col items-center">
            <button type="button" @click="generateFeeds()"
                    class="cursor-pointer rounded-lg outline-none focus:outline-none uppercase bg-blue-700 text-white py-2 px-4 text-center hover:bg-blue-400 flex items-center justify-center">
                <span class="material-icons mr-2" :class="{'animate-spin': isLoading}">{{ isLoading ? 'autorenew' : 'cloud_sync' }}</span> Regenerate Feeds
            </button>
            <div class="h-10">
                <p v-if="successMessage" class="text-green-800 uppercase">{{successMessage}}</p>
                <p v-if="errors.length" v-for="error in errors" class="text-red-800 uppercase">{{error}}</p>

            </div>
        </div>
    </div>

</template>

<script>
export default {
    name: "Feeds",
    components: {

    },
    props: {

    },
    data() {
        return {
            isLoading: false,
            successMessage: null,
            errors: []
        }
    },
    methods: {
        generateFeeds() {
            this.successMessage = null;
            this.errors = [];
            this.isLoading = true;
            let data = {
                'vendors': [
                    // 'AdmarktFeed',
                    // 'BeslistFeed',
                    // 'BolFeed',
                    // 'GoogleFeed',
                    // 'NetrivalsFeed',
                    // 'ShoprFeed',
                    // 'TradeTrackerFeed',
                    // 'FacebookFeed'
                ],
                'stores': [
                    // 'nubuitenbe',
                    // 'nubuitennl',
                    // 'buitenhandel',
                    // 'yurrtnl',
                    // 'yurrtbe',
                    // 'blokhutten.com',
                    // 'tuinhoutnl',
                    // 'hardhoutnl',
                    // 'tuinhuisjes.com',
                    // 'gardianl',
                ]
            }
            axios.post('feeds/generate', data)
                .then(response => {
                    this.successMessage = 'Feeds are being generated. You can leave this tab for now';
                })
                .catch(error => {
                    let errorResponse = error.response.data;
                    if(errorResponse?.message) {
                        this.errors.push(errorResponse?.message) ;
                    }



                }).finally(() => {
                this.isLoading = false
            })

        },

    },
    computed: {

    }
}
</script>

<style scoped lang="scss">


</style>
