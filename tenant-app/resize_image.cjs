const fs = require('fs');

// Read the current App.vue file
let appVue = fs.readFileSync('src/App.vue', 'utf8');

// Find the current image section
const oldImageSection = `      <!-- Problem Illustration -->
      <div class="max-w-5xl mx-auto">
        <div class="bg-white rounded-3xl p-8 shadow-lg border border-gray-100">
          <img 
            src="/images/lots_of_shitty_apps_in_one_place.png" 
            alt="Too many scattered apps and tools" 
            class="w-full h-auto rounded-2xl"
          />
        </div>
      </div>`;

// Replace with smaller version (75% size)
const newImageSection = `      <!-- Problem Illustration -->
      <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-3xl p-8 shadow-lg border border-gray-100">
          <img 
            src="/images/lots_of_shitty_apps_in_one_place.png" 
            alt="Too many scattered apps and tools" 
            class="w-3/4 h-auto rounded-2xl mx-auto"
          />
        </div>
      </div>`;

// Replace the section
appVue = appVue.replace(oldImageSection, newImageSection);

// Write the updated file
fs.writeFileSync('src/App.vue', appVue);
console.log('Image resized to 75% of original size!');