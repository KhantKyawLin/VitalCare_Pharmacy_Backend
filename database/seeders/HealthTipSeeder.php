<?php

namespace Database\Seeders;

use App\Models\HealthTip;
use App\Models\User;
use Illuminate\Database\Seeder;

class HealthTipSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::first();
        $authorId = $author ? $author->id : 1;

        $tips = [
            [
                'title' => 'Proper Antibiotic Usage: Why Completing Your Course Matters',
                'image_path' => 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=1200&auto=format&fit=crop&q=80',
                'content' => "Antibiotics are vital medicines designed to kill or slow down bacterial infections. When you begin taking an antibiotic, you often start feeling better after just a couple of days. However, stopping your prescription early is one of the leading causes of bacterial resistance.\n\n### Why You Must Finish the Entire Course\n1. **Residual Bacteria:** The weakest bacteria die off first, relieving early symptoms. The stronger, more resilient strains remain active longer.\n2. **Antibiotic Resistance:** If left uneliminated, surviving bacteria can mutate and develop resistance, rendering the medicine ineffective for future infections.\n3. **Relapse Risk:** Infections can recur quickly, often requiring stronger antibiotics with more severe side effects.\n\n### Pharmacist Safety Tips\n- Always follow the exact dosing schedule (e.g. every 8 or 12 hours) to maintain steady antibiotic levels in your bloodstream.\n- Do not share leftover antibiotics with family or friends.\n- Contact our pharmacy team if you experience adverse effects such as skin rashes or severe stomach cramps.",
                'is_published' => true,
            ],
            [
                'title' => 'Managing Seasonal Allergies: Antihistamines and Practical Relief',
                'image_path' => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=1200&auto=format&fit=crop&q=80',
                'content' => "Seasonal allergies trigger uncomfortable symptoms including sneezing, runny nose, itchy eyes, and nasal congestion when pollen, dust, or mold spores stimulate your immune system.\n\n### Choosing the Right Antihistamine\n- **First-Generation (e.g., Chlorpheniramine):** Highly effective for acute nighttime sneezing, but causes significant drowsiness.\n- **Second-Generation (e.g., Cetirizine, Loratadine, Fexofenadine):** Non-drowsy or low-sedation alternatives ideal for daytime work and daily productivity.\n\n### Practical Lifestyle Habits\n- Keep windows closed during high-pollen morning hours.\n- Shower and change clothes after spending extended time outdoors.\n- Use saline nasal sprays to gently flush allergens from your nasal passages without medication dependence.",
                'is_published' => true,
            ],
            [
                'title' => 'The Essential Guide to Vitamin D and Immune Health',
                'image_path' => 'https://images.unsplash.com/photo-1577401239170-897942555fb3?w=1200&auto=format&fit=crop&q=80',
                'content' => "Often nicknamed the 'sunshine vitamin', Vitamin D is crucial for bone mineral density, neuromuscular health, and a robust immune response.\n\n### Key Benefits of Optimal Vitamin D\n- **Immune Defense:** Supports white blood cells in fighting off respiratory and viral infections.\n- **Bone Strength:** Facilitates calcium absorption in the gut; deficiency increases risks of osteopenia and fractures.\n- **Mood Regulation:** Plays a pivotal role in neurotransmitter synthesis associated with energy and focus.\n\n### Safe Supplementation\nMost adults benefit from 1,000 to 2,000 IU daily, especially those working indoors. Vitamin D is fat-soluble, so take your supplement with a meal containing healthy dietary fats for maximum absorption.",
                'is_published' => true,
            ],
            [
                'title' => 'Safe Pain Management: Understanding Paracetamol vs. NSAIDs',
                'image_path' => 'https://images.unsplash.com/photo-1584017911766-d451b3d0e843?w=1200&auto=format&fit=crop&q=80',
                'content' => "Pain relief medications are among the most frequently used over-the-counter remedies, but picking the correct drug depends on the root cause of your pain.\n\n### Paracetamol (Acetaminophen)\n- **Best for:** Headaches, fever, general body aches, and toothache.\n- **Stomach Friendly:** Gentle on the stomach lining and safe for most individuals when taken as directed.\n- **Precaution:** Maximum 4,000mg (8 x 500mg tablets) in 24 hours to prevent liver toxicity. Avoid consuming alcohol concurrently.\n\n### NSAIDs (Ibuprofen, Diclofenac, Mefenamic Acid)\n- **Best for:** Inflammatory pain such as arthritis, muscle sprains, swelling, and menstrual cramps.\n- **Precaution:** Always take after food with water. Avoid if you have active stomach ulcers or chronic kidney disease.",
                'is_published' => true,
            ],
            [
                'title' => 'Hydration and Electrolytes: Preventing Dehydration in Warm Climates',
                'image_path' => 'https://images.unsplash.com/photo-1523362628745-0c100150b504?w=1200&auto=format&fit=crop&q=80',
                'content' => "Dehydration happens when your body loses more fluids and essential minerals than you intake. In hot weather or during episodes of diarrhea and fever, dehydration can set in rapidly.\n\n### Signs of Mild to Moderate Dehydration\n- Extreme thirst and dry mouth\n- Dark yellow or amber-colored urine\n- Fatigue, lightheadedness, and reduced concentration\n\n### When to Use Oral Rehydration Salts (ORS)\nPlain water is great for everyday hydration, but intense sweating or gastrointestinal illness depletes sodium and potassium. An ORS sachet dissolved in clean drinking water replenishes critical electrolytes and speeds up cellular rehydration.",
                'is_published' => true,
            ],
            [
                'title' => 'Protecting Your Eye Health in the Digital Screen Age',
                'image_path' => 'https://images.unsplash.com/photo-1588776814546-1ffcf47267a5?w=1200&auto=format&fit=crop&q=80',
                'content' => "With hours spent daily looking at smartphones, tablets, and computer monitors, digital eye strain (Computer Vision Syndrome) has become increasingly common.\n\n### The 20-20-20 Rule\nEvery 20 minutes, shift your eyes to look at an object at least 20 feet away for 20 seconds. This relaxes the ciliary muscles in your eyes and prevents focal fatigue.\n\n### Helpful Eye Care Practices\n- **Blink Consciously:** Screen time decreases blink rates by up to 50%, drying out the cornea. Use lubricating artificial tear eye drops if your eyes feel gritty.\n- **Adjust Lighting:** Position your workstation so that screen brightness matches surrounding ambient light.\n- **Positioning:** Keep monitors about an arm's length away, with the top of the display slightly below eye level.",
                'is_published' => true,
            ],
            [
                'title' => 'Heart Health: Daily Habits to Keep Blood Pressure in Check',
                'image_path' => 'https://images.unsplash.com/photo-1505751172876-fa1923c5c528?w=1200&auto=format&fit=crop&q=80',
                'content' => "High blood pressure (hypertension) is often called the 'silent killer' because it typically exhibits no obvious symptoms while placing heavy strain on your cardiovascular system.\n\n### Lifestyle Adjustments That Work\n1. **Reduce Dietary Sodium:** Keep daily sodium intake under 2,000mg. Watch out for hidden sodium in processed foods, canned soups, and instant noodles.\n2. **Regular Aerobic Activity:** 30 minutes of brisk walking 5 days a week helps maintain arterial elasticity.\n3. **Stress Management:** Chronic stress elevates cortisol and blood pressure. Dedicate 10 minutes daily to mindful breathing or relaxation.\n\n### Medication Adherence\nIf prescribed anti-hypertensive medications, take them consistently at the same time each day, even when you feel completely fine. Never discontinue without your physician's guidance.",
                'is_published' => true,
            ],
            [
                'title' => 'First Aid Essentials Every Household Medicine Cabinet Needs',
                'image_path' => 'https://images.unsplash.com/photo-1584017911766-d451b3d0e843?w=1200&auto=format&fit=crop&q=80',
                'content' => "Being prepared for unexpected cuts, burns, sprains, or allergic reactions starts with an organized and fully stocked home first aid kit.\n\n### Recommended Household Checklist\n- **Wound Care:** Sterile gauze pads, adhesive bandages in assorted sizes, medical tape, and antiseptic povidone-iodine or chlorhexidine wash.\n- **Topical Creams:** Burn soothing ointment, hydrocortisone cream for bug bites, and antibacterial ointment.\n- **Basic Medications:** Paracetamol, oral antihistamines, antacids, and oral rehydration salts.\n- **Diagnostic Tools:** Digital oral or infrared thermometer, medical scissors, tweezers, and a pulse oximeter.\n\nCheck expiration dates twice a year and store your kit in a cool, dry place away from children's reach.",
                'is_published' => true,
            ],
        ];

        foreach ($tips as $tipData) {
            HealthTip::updateOrCreate(
                ['title' => $tipData['title']],
                [
                    'user_id' => $authorId,
                    'image_path' => $tipData['image_path'],
                    'content' => $tipData['content'],
                    'is_published' => $tipData['is_published'],
                ]
            );
        }

        $this->command->info('Successfully seeded ' . count($tips) . ' Health Tips!');
    }
}
