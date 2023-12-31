<template>
    <AuthenticatedLayout>
        <div class="flex flex-col items-center justify-center mx-4 sm:mx-12 lg:mx-60 mt-16">
            <h2
                class="font-semibold text-4xl md:text-6xl text-gray-800 dark:text-gray-200 leading-tight font-bebas tracking-wider text-center">
                <span v-if="updateMode">Edit Video</span>
                <span v-else>Add New Video</span>
            </h2>
            <form @submit.prevent="submit" class="bg-gray-700 p-4 md:p-10 rounded my-8 w-full">
                <div>
                    <InputLabel for="title" value="Title" />

                    <TextInput id="title" type="text" class="mt-1 block w-full" v-model="form.title" required autofocus
                        autocomplete="title" />

                    <InputError class="mt-2" :message="form.errors.title" />
                </div>

                <div class="mt-4">
                    <InputLabel for="description" value="Description" />

                    <TextArea id="description" type="description" class="mt-1 block w-full" v-model="form.description"
                        required autocomplete="username" />

                    <InputError class="mt-2" :message="form.errors.description" />
                </div>

                <div class="mt-4">
                    <InputLabel for="youtube_url" value="YouTube Video Id" />
                    <p class="my-2 text-xs text-white italic">This will be the bundle of letters & numbers at the end of the
                        link <br>e.g the 'HUWqeq_H9mc' in https://www.youtube.com/watch?v=HUWqeq_H9mc</p>

                    <TextInput id="youtube_url" type="text" class="mt-1 block w-full" v-model="form.youtube_url" required
                        autofocus autocomplete="youtube_url" />
                    <InputError class="mt-2" :message="form.errors.youtube_url" />
                </div>

                <div class="mt-4">
                    <InputLabel for="cover_image" value="Cover Image" />

                    <CoverImageUploader :coverImage="coverImage" @imageChanged="onImageChange"
                        @invalidFile="handleInvalidFile" />

                    <InputError class="mt-2" :message="form.errors.coverImage" />
                </div>

                <div class="mt-4">
                    <InputLabel for="featured_users" value="Featured Users" />

                    <div class="flex flex-wrap justify-start align-center">
                        <a 
                            v-for="(person, index) in people" 
                            :key="index" 
                            class="cursor-pointer w-64 bg-[#20354b] hover:bg-indigo-600 hover:border-blue-600 hover:border rounded-2xl px-8 py-6 shadow-lg me-4 my-4 transition-all relative group overflow-hidden"
                            :class="{ 'bg-green-600': selectedPeople.some(user => user.id === person.id) }"
                            @click="togglePersonSelection(person)"
                        >
                            <div class="mt-6 w-fit mx-auto">
                                <img src="https://api.lorem.space/image/face?w=120&h=120&hash=bart89fe" class="rounded-full w-28" alt="profile picture">
                            </div>

                            <div class="mt-8">
                                <h2 class="text-white text-center font-bold text-2xl tracking-wide">{{ person.name }}</h2>
                            </div>

                            <div class="mt-3 text-white text-sm text-center">
                                <span>{{ person.family }}</span>
                            </div>
                        </a>

                    </div>

                    <InputError class="mt-2" :message="form.errors.cover_image" />
                </div>

                <progress v-if="form.progress" :value="form.progress.percentage" max="100">
                    {{ form.progress.percentage }}%
                </progress>

                <div class="flex items-center justify-end mt-4">
                    <PrimaryButton class="ms-4" :class="{ 'opacity-25': form.processing || invalidFile }"
                        :disabled="form.processing || invalidFile">
                        Submit
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import TextArea from '@/Components/TextArea.vue';
import CoverImageUploader from '@/Components/CoverImageUploader.vue';
import { useForm, router } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2'

const props = defineProps({
    updateMode: {
        type: Boolean,
        default: false,
    },
    video: {
        type: Object,
        default: () => ({}),
    },
    people: {
        type: Object,
        default: () => ({}),
    }
});

const form = useForm({
    title: ref(props.video.title ?? ''),
    description: ref(props.video.description ?? ''),
    youtube_url: ref(props.video.youtube_url ?? ''),
    featured_users: ref(ref(props.video.featured_users ?? ''),),
});

let coverImage = ref(props.video.cover_image ?? '');
let coverImageHasChanged = ref(false);
let invalidFile = ref(false);

const handleInvalidFile = (value) => {
    invalidFile.value = value;
};

const emit = defineEmits();

const onImageChange = (newImage) => {
    coverImage = newImage;
    coverImageHasChanged.value = true;
};

const selectedPeople = ref(form.featured_users);

const togglePersonSelection = (person) => {
  const index = selectedPeople.value.findIndex((selectedPerson) => selectedPerson.id === person.id);

  if (index === -1) {
    selectedPeople.value.push(person);
  } else {
    selectedPeople.value.splice(index, 1);
  }

  form.featured_users = selectedPeople.value;
};

const submit = async () => {
    // Set default image if none chosen
    if (coverImage === '') {
        coverImage = 'https://images.unsplash.com/photo-1550399504-8953e1a6ac87?q=80&w=2829&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D';
    }

    /** TODO: I cannot get Inertia.js to use formData to send the image and other form data at the same time,
     * it messes up the other data and Laravel does not want to parse it at all. So I have done the following instead:
     * UPDATE: Send a request to a separate image upload method first and if successful, send the rest of the form data to the usual route (the redirect gets handled in the method)
     * STORE: I use axios to send the formData and get the new video.id and the success message and assign these to new variables. Then, I only send a request
     * to the uploadImnage route of the coverimage is actually there and not default. Otherwise it will just get the defaultImage URL and store that in the db
     * as the cover imgae
     * 
     * Need to come back to this once I have worked out how to send form data with the image and parsed with Laravel successfully, then we can really make this code cleaner and shorter!
    */

    if (props.updateMode) {
        try {
            const formData = new FormData();
            formData.append('cover_image', coverImage);

            if (coverImageHasChanged.value) {
                await axios.post(`/video/${props.video.id}/cover-image-upload`, formData, {
                    headers: {
                        "Content-Type": "multipart/form-data",
                    },
                });
            }

            await form.put(route('video.update', props.video.id));
        } catch (error) {
            console.log({ error });
            Swal.fire({
                title: "Error",
                text: `There was an issue uploading your image (${error?.response?.data?.message ? error.response.data.message : 'please try again'})`,
                icon: "error",
            });

            throw error;
        }
    } else {
        try {
            let videoId;
            let message;

            // Create a separate request item for cover_image only if coverImage is blank
            const coverImageValue = coverImage.value !== '' ? {} : { cover_image: 'default_cover_image' };

            const requestData = { ...form, ...coverImageValue };

            const response = await axios.post(route('video.store', requestData));
            videoId = response.data.video.id;
            message = response.data.message;

            if (coverImage.value !== '') {
                const formData = new FormData();
                formData.append('cover_image', coverImage);

                await axios.post(`/video/${videoId}/cover-image-upload`, formData, {
                    headers: {
                        "Content-Type": "multipart/form-data",
                    },
                }).then((res) => {
                    console.log(res);
                }).catch((err) => {
                    console.log(err);
                });
            }

            Swal.fire({
                title: response?.data?.message?.type,
                text: response?.data?.message?.text,
                icon: response?.data?.message?.type.toLowerCase(),
                timer: 2000,
                timerProgressBar: true,
                showConfirmButton: false,
            }).then(() => {
                router.visit(`/video/${videoId}`);
            });


        } catch (error) {
            console.log({ error });
            Swal.fire({
                title: "Error",
                text: `There was an issue uploading your image (${error?.response?.data?.message ? error.response.data.message : 'please try again'})`,
                icon: "error",
            });

            throw error;
        }


    }
};

</script>
  
  