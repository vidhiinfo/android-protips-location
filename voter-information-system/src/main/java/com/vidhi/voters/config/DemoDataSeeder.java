package com.vidhi.voters.config;

import com.vidhi.voters.model.Gender;
import com.vidhi.voters.model.RelationType;
import com.vidhi.voters.model.Voter;
import com.vidhi.voters.repo.VoterRepository;
import org.springframework.boot.CommandLineRunner;
import org.springframework.context.annotation.Profile;
import org.springframework.stereotype.Component;

import java.util.Random;

/** Fills the in-memory H2 database with sample voters. Only active with --spring.profiles.active=demo. */
@Component
@Profile("demo")
public class DemoDataSeeder implements CommandLineRunner {

    private final VoterRepository repo;

    public DemoDataSeeder(VoterRepository repo) { this.repo = repo; }

    @Override
    public void run(String... args) {
        if (repo.count() > 0) return;
        String[] male = {"Ramesh", "Suresh", "Mahesh", "Rajesh", "Amit", "Vikas", "Sunil", "Anil", "Deepak", "Manoj", "Rohit", "Sanjay"};
        String[] female = {"Sunita", "Anita", "Kavita", "Pooja", "Neha", "Priya", "Rekha", "Seema", "Geeta", "Meena", "Ritu", "Asha"};
        String[] surname = {"Sharma", "Verma", "Saini", "Gupta", "Singh", "Yadav", "Meena", "Jain", "Kumar", "Choudhary"};
        String[][] assemblies = {{"45", "Jaipur North"}, {"46", "Jaipur South"}, {"52", "Ajmer City"}};
        String[] villages = {"Ward 1", "Ward 2", "Ward 3", "Shastri Nagar", "Malviya Nagar", "Vaishali Nagar"};
        Random rnd = new Random(42);

        for (int i = 1; i <= 300; i++) {
            Voter v = new Voter();
            String[] a = assemblies[rnd.nextInt(assemblies.length)];
            boolean isMale = rnd.nextBoolean();
            String sn = surname[rnd.nextInt(surname.length)];
            v.setEpicNo(String.format("RJX%07d", 1000000 + i));
            v.setName((isMale ? male[rnd.nextInt(male.length)] : female[rnd.nextInt(female.length)]) + " " + sn);
            v.setGender(isMale ? Gender.MALE : Gender.FEMALE);
            boolean married = !isMale && rnd.nextInt(3) > 0;
            v.setRelationType(married ? RelationType.HUSBAND : RelationType.FATHER);
            v.setRelativeName(male[rnd.nextInt(male.length)] + " " + sn);
            v.setAge(18 + rnd.nextInt(70));
            v.setAssemblyNo(Integer.parseInt(a[0]));
            v.setAssemblyName(a[1]);
            v.setBoothNo(1 + rnd.nextInt(12));
            v.setBoothName("Govt. School Room " + (1 + rnd.nextInt(4)));
            v.setSerialNo(i);
            v.setHouseNo(String.valueOf(1 + rnd.nextInt(400)));
            v.setAddress("Street " + (1 + rnd.nextInt(30)));
            v.setVillageWard(villages[rnd.nextInt(villages.length)]);
            v.setDistrict(a[1].startsWith("Ajmer") ? "Ajmer" : "Jaipur");
            v.setPincode(a[1].startsWith("Ajmer") ? "305001" : "30200" + (1 + rnd.nextInt(9)));
            if (rnd.nextInt(10) < 8) v.setMobile(String.valueOf(9000000000L + rnd.nextInt(999999999)));
            repo.save(v);
        }
    }
}
