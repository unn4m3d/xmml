package io.github.unn4m3d.xmml;

import java.util.ArrayList;

import com.googlecode.lanterna.TerminalFacade;
import com.googlecode.lanterna.gui.*;
import com.googlecode.lanterna.input.Key;
import com.googlecode.lanterna.gui.component.*;
import com.googlecode.lanterna.gui.dialog.*;
import com.googlecode.lanterna.gui.layout.LayoutManager;
import com.googlecode.lanterna.gui.layout.LayoutParameter;
import com.googlecode.lanterna.gui.layout.LinearLayout;

import net.launcher.*;
import net.launcher.components.*;

public class Launcher extends Window {

	protected Thread listenerThread;
	//protected Thread actionListenerThread;
	protected TextBox loginField;
	protected PasswordBox passField;
	protected RadioCheckBoxList servers;
	protected int serverIndex = 0;
	/**
	 * @param args
	 */
	public static void main(String[] args) {
		final GUIScreen s = TerminalFacade.createGUIScreen();
		Launcher l = new Launcher(s);
		s.getScreen().startScreen();
		s.showWindow(l, GUIScreen.Position.FULL_SCREEN);
		try {
			l.listenerThread.join();
		} catch (InterruptedException e) {
			// TODO Auto-generated catch block
			e.printStackTrace();
		}
	}
	
	public Launcher(final GUIScreen s){
		super(Settings.title);
		//Set up login form
		this.addComponent(new Label("Press Esc or Ctrl-C to quit"), new LayoutParameter(""));
		
		Panel mainpanel = new Panel(new Border.Invisible(),Panel.Orientation.HORISONTAL);
		
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
		
		actions.addComponent(new Button("Login",new Action(){

			@Override
			public void doAction() {
				// TODO Auto-generated method stub
				
			}
			
		}),LinearLayout.GROWS_HORIZONTALLY);
		
		
		fields.addComponent(sp,LinearLayout.MAXIMIZES_HORIZONTALLY);
		mainpanel.addComponent(fields);
		mainpanel.addComponent(actions);
		this.addComponent(mainpanel);
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
