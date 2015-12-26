package io.github.unn4m3d.xmml;

import io.github.unn4m3d.xmml.files.Guard;
import io.github.unn4m3d.xmml.files.ZipUtils;

import java.io.File;
import java.io.IOException;
import java.net.MalformedURLException;
import java.util.ArrayList;
import java.util.Formatter;

import org.json.simple.JSONArray;
import org.json.simple.JSONObject;
import org.json.simple.parser.ParseException;

import com.googlecode.lanterna.TerminalFacade;
import com.googlecode.lanterna.gui.*;
import com.googlecode.lanterna.gui.Component.Alignment;
import com.googlecode.lanterna.input.Key;
import com.googlecode.lanterna.terminal.Terminal.Color;
import com.googlecode.lanterna.gui.component.*;
import com.googlecode.lanterna.gui.dialog.*;
import com.googlecode.lanterna.gui.layout.LayoutManager;
import com.googlecode.lanterna.gui.layout.LayoutParameter;
import com.googlecode.lanterna.gui.layout.LinearLayout;

import net.launcher.*;
import net.launcher.components.*;
import net.launcher.utils.ActionListener;
import net.launcher.utils.ClientUtils;
import net.launcher.utils.FileDownloader;

import io.github.unn4m3d.xmml.Misc;

public class Launcher extends Window {

	protected Thread listenerThread;
	//protected Thread actionListenerThread;
	protected TextBox loginField;
	protected PasswordBox passField;
	protected RadioCheckBoxList servers;
	protected int serverIndex = 0;
	protected Label status;
	protected ProgressBar updateSt;
	protected Label progress;
	//protected ProgressBar currUpdateSt;
	/**
	 * @param args
	 */
	public static void main(String[] args) {
		TempSettings.client = Settings.servers[0];
		try {
			Log.send(ClientUtils.getMcDir().getCanonicalPath());
		} catch (IOException e) {
			// TODO Auto-generated catch block
			e.printStackTrace();
		}
		final GUIScreen s = TerminalFacade.createGUIScreen();
		Launcher l = new Launcher(s);
		s.getScreen().startScreen();
		s.showWindow(l, GUIScreen.Position.FULL_SCREEN);
		try {
			l.listenerThread.join();
		} catch (Throwable e) {
			// TODO Auto-generated catch block
			e.printStackTrace();
		}
	}
	
	public Launcher(final GUIScreen s){
		super(Settings.title);
		//Set up login form
		this.addComponent(new Label("Press Esc or Ctrl-C to quit"), new LayoutParameter(""));
		status = new Label("...");
		this.addComponent(status);
		final Panel mainpanel = new Panel(new Border.Invisible(),Panel.Orientation.HORISONTAL);
		
		Panel fields = new Panel("Login Form", Panel.Orientation.VERTICAL);
		
		Panel lp = new Panel("Login",Panel.Orientation.HORISONTAL);
		//this.addComponent(new Label("Login :\t"));
		
		loginField = new TextBox("Login",32);
		lp.addComponent(loginField,new LayoutParameter(""));
		fields.addComponent(lp);
		
		Panel pp = new Panel("Password",Panel.Orientation.HORISONTAL);
		//this.addComponent(new Label("Password :\t"), new LayoutParameter(""));
		
		passField = new PasswordBox("Password",32);
		pp.addComponent(passField, new LayoutParameter(""));
		fields.addComponent(pp);
		//this.addComponent(new Label("Server :\t"), new LayoutParameter(""));
		
		Panel sp = new Panel("Server",Panel.Orientation.HORISONTAL);
		
		servers = new RadioCheckBoxList();
		for(ServerInfo serv : Settings.servers){
			servers.addItem(serv.name);
		}
		servers.setSelectedItem(0);
		sp.addComponent(servers, LinearLayout.MAXIMIZES_HORIZONTALLY);
		
		Panel actions = new Panel("Actions",Panel.Orientation.VERTICAL);
		
		actions.addComponent(new Button("Quit ",new Action(){

			@Override
			public void doAction() {
				// TODO Auto-generated method stub
				System.exit(0);
			}
			
		}),LinearLayout.GROWS_HORIZONTALLY);
		
		progress = new Label("...");
		
		final Panel updpanel = new Panel(new Border.Invisible(),Panel.Orientation.HORISONTAL);
		
		actions.addComponent(new Button("Login",new Action(){

			@Override
			public void doAction() {
				Log.send("LOGIN");
				final JSONObject a;
				try {
					a = Actions.auth(loginField.getText(), passField.getText());
					status.setText((String) a.get("text"));
					if((boolean) a.get("error") == true){
						status.setTextColor(Color.RED);
						return;
					}
					else
						status.setTextColor(Color.GREEN);
					Log.send(a.toJSONString());
					
					JSONArray fs = (JSONArray)a.get("files");
					
					String hash = "";
					
					for(int i = 0; i < fs.size(); i++){
						Object o = fs.get(i);
						if(((String)((JSONObject)o).get("path")).matches("minecraft\\.jar"))
							hash = ((String)((JSONObject)o).get("md5"));
					}
					
					final boolean dzip = !(Actions.checkMods((JSONArray) a.get("files")));
					final boolean djar = !(new File(ClientUtils.getMcDir().getAbsolutePath(),"bin/minecraft.jar").exists()
							&& Guard.getMD5(new File(ClientUtils.getMcDir(),"bin/minecraft.jar").getCanonicalPath()) == hash &&
							hash != "");					
					if(dzip){
						final Thread t = new Thread(){
							public void run(){
								final Thread th = this;
								Button b = new Button("Cancel",new Action(){

									@Override
									public void doAction() {
										// TODO Auto-generated method stub
										th.interrupt();
										updpanel.setVisible(false);
									}
									
								});
								updpanel.addComponent(b);
								try {
									new File(ClientUtils.getMcDir(),"temp").mkdirs();
									String in = "",out = "";
									if(dzip){
										Formatter f = new Formatter();
										in = f.format(
												"%s/clients/%s/client.zip",
												Settings.webpath,
												TempSettings.client.getName()
										).toString();
										
										f.close();
										f = new Formatter();
										out = /*new File*/(f.format(
												"%s/temp/client.zip",
												ClientUtils.getMcDir()
										).toString())/*.getCanonicalPath()*/;
										
										f.close();
										
									}else if(djar){
										Formatter f = new Formatter();
										in = f.format(
												"%s/clients/%s/bin/minecraft.jar",
												Settings.webpath,
												TempSettings.client.getName()
										).toString();
										
										f.close();
										f = new Formatter();
										
										out = (f.format(
												"%s/bin/minecraft.jar",
												ClientUtils.getMcDir()
										).toString());
										
										f.close();
									}
									
									if(dzip || djar){
										final FileDownloader f = new FileDownloader(in,out);
									
										f.addUpdateListener(new ActionListener(){
											public void update(Object o){
												//updateSt.setProgress((long)o/f.size);
												status.setText(Misc.long2fsize((long)o) + 
													"/" + Misc.long2fsize(f.size) + " bytes"
												);
												//System.out.println((long)o);
											}
										});
										f.start();
										if(dzip){
											ZipUtils.unzip(ClientUtils.getMcDir().getCanonicalPath(), new File(ClientUtils.getMcDir(),"temp/client.zip").getCanonicalPath());
										}
									}
									status.setText("Launching...");
									ClientUtils.updateURLs();
									new Game(a);
									status.setText("OK!");
								} catch ( Exception e) {
									// TODO Auto-generated catch block
									e.printStackTrace();
									//status.setText(e.getMessage());
									status.setTextColor(Color.RED);
									th.interrupt();
								} catch (Throwable e) {
									e.printStackTrace();
									status.setText(e.toString());
									status.setTextColor(Color.RED);
								}
							}
						};
						t.setName("Updater thread");
						t.start();
					}
					status.setText("Launching...");
					try {
						ClientUtils.updateURLs();
						new Game(a);
						status.setText("OK!");
					} catch (Throwable e) {
						status.setText(e.toString());
						status.setTextColor(Color.RED);
						e.printStackTrace();
						//e.getCause().printStackTrace();
					}
					
				} catch (Exception e) {
					e.printStackTrace();
					status.setText(e.toString());
					status.setTextColor(Color.RED);
				}
			}
			
		}),LinearLayout.GROWS_HORIZONTALLY);
		
		//Settings panel
		
		
		/*actions.addComponent(new Button("Settings",new Action(){

			@Override
			public void doAction() {
				// TODO Auto-generated method stub
				mainpanel.setVisible(false);
				updpanel.setVisible(true);
				//s.getActiveWindow().notify();
			}
			
		}),LinearLayout.GROWS_HORIZONTALLY);*/
		
		
		fields.addComponent(sp,LinearLayout.MAXIMIZES_HORIZONTALLY);
		mainpanel.addComponent(fields);
		mainpanel.addComponent(actions);
		
		updateSt = new ProgressBar(32);
		updateSt.setProgress(0.0);
		
		updpanel.addComponent(new Label("Update state :"));
		
		updpanel.addComponent(updateSt);
		
		updpanel.addComponent(progress);
		
		this.addComponent(mainpanel);
		mainpanel.addComponent(updpanel);
		updpanel.setVisible(false);
		updpanel.setAlignment(Alignment.TOP_LEFT);
		listenerThread = new Thread(){
			public void run(){
				//System.out.println("KL Started!");
				while(true){
					Key key = s.getScreen().readInput();
					if(key == null) continue;
					//System.out.printf("Key pressed : %s\n", (key.getKind() == Key.Kind.NormalKey ? key.getCharacter() : key.getKind().toString()) );
					if(
						key.getKind() == Key.Kind.Escape || //Exit on escape
						(	
							key.getKind() == Key.Kind.NormalKey && 
							String.valueOf(key.getCharacter()).matches("[cC]") && 
							key.isCtrlPressed() //Escape on Ctrl-C
						)	
					){
						s.getScreen().stopScreen();
						System.exit(0);
					}else{
						s.getActiveWindow().onKeyPressed(key);
					}
					TempSettings.client = Settings.servers[servers.getSelectedIndex()];
				}
				
			}
		};
		listenerThread.setName("Key Listener");
		listenerThread.start();
		
	}

}
