
<script>
export default {
    data() {
        return {
            message: 'Hello, World!',
            showWelcome: true
        }
    }
}
</script>

<template>
    <div class="index-page h-screen">
        <UAlert
            v-if="showWelcome"
            title="Welcome to Test Mart!"
            description="This is the main landing page of the application."
            :avatar="{src: 'https://github.com/nuxt.png'}"
            color="neutral"
            close
            @update:open="showWelcome = $event"
        />
        <!-- <button @click="message += '!'">Hello "!"</button>
        <p>{{ message }}</p> -->
    </div>
</template>

<style scoped>
h1 {
    text-align: center;
}
</style>
