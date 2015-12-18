package io.github.unn4m3d.xmml;

import java.io.File;
import java.util.ArrayList;

import net.launcher.*;
import net.launcher.utils.*;

public class MainClass {

	/**
	 * @param args
	 */
	public static void main(String[] args) {
		//System.out.println("Launch");
		try {

			String jarpath = Launcher.class.getProtectionDomain().getCodeSource().getLocation().toURI().getPath();
			int memory = (int)Config.get("memory", 1024);
			
			ArrayList<String> params = new ArrayList<String>();
           
			params.add("java");
			params.add("-Xmx"+memory+"m");
			params.add("-Xms"+memory+"m");
			params.add("-XX:MaxPermSize=128m");
			params.add("-Dfile.encoding=UTF-8");
			if(System.getProperty("os.name").toLowerCase().startsWith("mac"))
			{
				params.add("-Xdock:name=Minecraft");
				params.add("-Xdock:icon="+ClientUtils.getAssetsDir().toString()+"/favicon.png");
			}
			params.add("-classpath");
			params.add(jarpath);
			params.add(Launcher.class.getCanonicalName());

			ProcessBuilder pb = new ProcessBuilder(params);
			pb.directory(new File(ClientUtils.getAssetsDir().toString()));
			Process process = pb.start();
			if (process == null) throw new Exception("Launcher can't be started!");
			System.exit(0);
		} catch (Exception e)
		{
			e.printStackTrace();
		}

	}

}
